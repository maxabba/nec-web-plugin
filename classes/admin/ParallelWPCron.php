<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . '\ParallelWPCron')) {
    class ParallelWPCron
    {
        private $max_execution_time;
        private $max_children;
        private $wp_load_path;
        private $is_enabled;
        private $cron_interval;

        public function __construct()
        {
            $this->load_config();

            if (!$this->is_enabled) {

                //delete the cron job if exists
                $timestamp = wp_next_scheduled('run_parallel_cron');
                wp_unschedule_event($timestamp, 'run_parallel_cron');

                return;
            }
            $this->set_wp_load_path();
            $this->detect_fpm_limits();
            $this->ensure_cron_interval();

            $this->init();
        }

        private function load_config()
        {
            $this->is_enabled = defined('PARALLEL_CRON_ENABLED') ? PARALLEL_CRON_ENABLED : false;
            $this->cron_interval = defined('PARALLEL_CRON_INTERVAL') ? PARALLEL_CRON_INTERVAL : 'every_minute';
        }

        private function set_wp_load_path()
        {
            $this->wp_load_path = ABSPATH . 'wp-load.php';
        }

        private function detect_fpm_limits()
        {
            $this->max_execution_time = ini_get('max_execution_time');
            if ($this->max_execution_time == 0 || $this->max_execution_time > 300) {
                $this->max_execution_time = 300;
            }

            $this->max_children = $this->get_fpm_max_children();
            if (!$this->max_children) {
                $this->max_children = 5;
            }
        }

        private function get_fpm_max_children()
        {
            return 32; // Valore predefinito se non riusciamo a determinare max_children
        }

        private function ensure_cron_interval()
        {
            add_filter('cron_schedules', [$this, 'add_cron_interval']);
        }

        public function add_cron_interval($schedules)
        {
            if (!isset($schedules['every_minute'])) {
                $schedules['every_minute'] = array(
                    'interval' => 60,
                    'display' => __('Every Minute')
                );
            }
            return $schedules;
        }

        public function init()
        {
            if ($this->is_enabled) {
                add_action('init', [$this, 'disable_wp_cron']);
                add_action('init', [$this, 'schedule_custom_cron']);
            }
        }

        public function disable_wp_cron()
        {
            if (!defined('DISABLE_WP_CRON')) {
                define('DISABLE_WP_CRON', true);
            }
        }

        public function schedule_custom_cron()
        {
            if (!wp_next_scheduled('run_parallel_cron')) {
                wp_schedule_event(time(), $this->cron_interval, 'run_parallel_cron');
            }
            add_action('run_parallel_cron', [$this, 'execute_cron_jobs']);
        }

        public function execute_cron_jobs()
        {
            ignore_user_abort(true);
            set_time_limit($this->max_execution_time);

            if (!defined('ABSPATH')) {
                require_once($this->wp_load_path);
            }

            $cron_jobs = _get_cron_array();
            $gmt_time = microtime(true);
            $children = 0;

            foreach ($cron_jobs as $timestamp => $cronhooks) {
                if ($timestamp > $gmt_time) {
                    continue;
                }

                foreach ($cronhooks as $hook => $keys) {
                    if (function_exists('pcntl_fork') && $children < $this->max_children) {
                        $pid = pcntl_fork();
                        if ($pid == -1) {
                            error_log('Could not fork for cron job: ' . $hook);
                        } elseif ($pid) {
                            // Parent process
                            $children++;
                        } else {
                            // Child process
                            $this->run_cron_job($timestamp, $hook, $keys);
                            exit();
                        }
                    } else {
                        $this->run_cron_job($timestamp, $hook, $keys);
                    }
                }
            }

            if (function_exists('pcntl_wait')) {
                while ($children > 0 && pcntl_wait($status) > 0) {
                    $children--;
                }
            }
        }

        private function run_cron_job($timestamp, $hook, $keys)
        {
            foreach ($keys as $k => $v) {
                $schedule = $v['schedule'];

                if ($schedule != false) {
                    wp_reschedule_event($timestamp, $schedule, $hook, $v['args']);
                }

                wp_unschedule_event($timestamp, $hook, $v['args']);

                do_action_ref_array($hook, $v['args']);
            }
        }
    }
}