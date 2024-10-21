<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists(__NAMESPACE__ . 'DbClass')) {
    class DbClass
    {
        private $table_name;

        public function __construct()
        {
            global $wpdb;
            $this->table_name = $wpdb->prefix . 'dkm_comuni';
        }

        public function create_table()
        {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            global $wpdb;

            //check if the table already exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") == $this->table_name) {
                return;
            }

            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $this->table_name (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    nome varchar(255) NOT NULL,
                    codice varchar(255) NOT NULL,
                    zona_nome varchar(255) NOT NULL,
                    zona_codice varchar(255) NOT NULL,
                    regione_codice varchar(255) NOT NULL,
                    regione_nome varchar(255) NOT NULL,
                    provincia_codice varchar(255) NOT NULL,
                    provincia_nome varchar(255) NOT NULL,
                    sigla varchar(255) NOT NULL,
                    codiceCatastale varchar(255) NOT NULL,
                    cap varchar(255) NOT NULL,
                    popolazione int NOT NULL,
                    PRIMARY KEY  (id)
                ) $charset_collate;";
           $result =  dbDelta($sql);
           if (!$result) {
               error_log('Table creation failed: ' . $wpdb->last_error, 0);
           }else{
               $this->import_data();

           }
        }


        public function import_data()
        {
            $json_data = file_get_contents(DOKAN_SELECT_PRODUCTS_PLUGIN_PATH . 'data/comuni.json');
            $comuni = json_decode($json_data, true);

            global $wpdb;
            foreach ($comuni as $comune) {
                $result = $wpdb->insert($this->table_name, [
                    'nome' => $comune['nome'],
                    'codice' => $comune['codice'],
                    'zona_nome' => $comune['zona']['nome'],
                    'zona_codice' => $comune['zona']['codice'],
                    'regione_nome' => $comune['regione']['nome'],
                    'regione_codice' => $comune['regione']['codice'],
                    'provincia_nome' => $comune['provincia']['nome'],
                    'provincia_codice' => $comune['provincia']['codice'],
                    'sigla' => $comune['sigla'],
                    'codiceCatastale' => $comune['codiceCatastale'],
                    'cap' => implode(',', $comune['cap']),
                    'popolazione' => $comune['popolazione']
                ]);
                if (!$result) {
                    error_log('Data import failed: ' . $wpdb->last_error, 0);
                }
            }

        }

        public function get_comuni_by_provincia($provincia)
        {
            if (empty($provincia)) {
                return [];
            }

            global $wpdb;
            $sql = $wpdb->prepare("SELECT nome FROM $this->table_name WHERE provincia_nome = %s ORDER BY nome ASC", $provincia);
            $result = $wpdb->get_results($sql, ARRAY_A);

            //map the result to get only the name of the comune
            $result = array_map(function ($comune) {
                return $comune['nome'];
            }, $result);

            return $result;
        }

        public function get_all_Province()
        {
            global $wpdb;
            $sql = "SELECT DISTINCT provincia_nome FROM $this->table_name ORDER BY provincia_nome ASC";
            $result = $wpdb->get_results($sql, ARRAY_A);
            //order the result by province name

            return $result;
        }

        //get provincia by comune
        public function get_provincia_by_comune($comune)
        {
            global $wpdb;
            $sql = $wpdb->prepare("SELECT provincia_nome FROM $this->table_name WHERE nome = %s", $comune);
            $result = $wpdb->get_var($sql);
            return $result;
        }


        //search comune by name and get corrispondence name with fulltext search
        public function search_comune($comune)
        {
            global $wpdb;
            $sql = $wpdb->prepare("SELECT nome FROM $this->table_name WHERE MATCH (nome) AGAINST (%s IN NATURAL LANGUAGE MODE)", $comune);
            $result = $wpdb->get_results($sql, ARRAY_A);
            return $result;
        }


        public function get_provincia_by_sigla($sigla)
        {
            global $wpdb;
            $sigla = strtoupper($sigla);

            $sql = $wpdb->prepare("SELECT provincia_nome FROM $this->table_name WHERE sigla = %s", $sigla);
            $result = $wpdb->get_var($sql);

            if (!$result) {
                return '';
            }
            return $result;
        }

    }
}

