<?php

namespace Dokan_Mods;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!class_exists(__NAMESPACE__ . '\CloudFlareGeo')) {
    class CloudFlareGeo
    {
        private $worker_url;
        private $worker_token;

        public function __construct()
        {

            add_action('admin_menu', [$this, 'add_admin_menu']);

            // Recupera le impostazioni salvate per il Worker
            $this->worker_url = get_option('worker_url', '');
            $this->worker_token = get_option('worker_token', '');
        }


        public function add_admin_menu()
        {
            // Aggiunge la pagina di configurazione per URL Worker e Token
            add_submenu_page(
                'dokan-mod',
                'Impostazioni Worker',
                'Impostazioni Worker',
                'manage_options',
                'worker-settings',
                array($this, 'worker_settings_page')
            );
        }


        public function worker_settings_page()
        {
            // Salva le impostazioni se il modulo è stato inviato
            if (isset($_POST['submit'])) {
                update_option('worker_url', sanitize_text_field($_POST['worker_url']));
                update_option('worker_token', sanitize_text_field($_POST['worker_token']));
                echo '<div class="notice notice-success is-dismissible"><p>Impostazioni salvate.</p></div>';
            }

            // Recupera i valori salvati
            $worker_url = esc_attr(get_option('worker_url', ''));
            $worker_token = esc_attr(get_option('worker_token', ''));

            ?>
            <div class="wrap">
                <h1><?php esc_html_e('Impostazioni del Worker', 'textdomain'); ?></h1>

                <!-- Form per inserire URL e Token -->
                <form method="post" action="">
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">
                                <label for="worker_url"><?php esc_html_e('URL del Worker', 'textdomain'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="worker_url" name="worker_url" value="<?php echo $worker_url; ?>"
                                       class="regular-text"/>
                            </td>
                        </tr>

                        <tr valign="top">
                            <th scope="row">
                                <label for="worker_token"><?php esc_html_e('Token del Worker', 'textdomain'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="worker_token" name="worker_token"
                                       value="<?php echo $worker_token; ?>" class="regular-text"/>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Salva Impostazioni'); ?>
                </form>

                <!-- Guida completa per configurare il Worker su Cloudflare -->
                <h2><?php esc_html_e('Guida alla Configurazione del Worker su Cloudflare', 'textdomain'); ?></h2>
                <p>Segui i passaggi qui sotto per creare e configurare il Worker su Cloudflare:</p>

                <ol>
                    <li><strong>Crea il Worker su Cloudflare:</strong>
                        <ul>
                            <li>Accedi alla tua dashboard di Cloudflare.</li>
                            <li>Seleziona il sito su cui vuoi configurare il Worker.</li>
                            <li>Vai su <em>Workers</em> nella barra laterale.</li>
                            <li>Clicca su <em>Create a Worker</em> e assegna un nome al Worker.</li>
                            <li>Copia e incolla il seguente codice nel Worker editor:</li>
                        </ul>
                        <!-- Box del codice con il pulsante di copia -->
                        <div class="code-container">
                <pre><code id="workerCode">export default {
  async fetch(request, env) {
    const { headers } = request;
    const authHeader = headers.get('Authorization');
    const secretToken = env.SECRET_TOKEN; // Variabile segreta definita tramite wrangler

    // Verifica se l'header Authorization è presente
    if (!authHeader || !authHeader.startsWith('Bearer ')) {
      return new Response(JSON.stringify({ error: 'Token mancante o non valido' }), {
        status: 401,
        headers: { 'Content-Type': 'application/json' },
      });
    }

    const token = authHeader.substring(7); // Rimuove 'Bearer ' dall'inizio

    // Confronta il token fornito con il token segreto memorizzato
    if (token !== secretToken) {
      return new Response(JSON.stringify({ error: 'Token non autorizzato' }), {
        status: 403,
        headers: { 'Content-Type': 'application/json' },
      });
    }

    // Estrae i dati di geolocalizzazione da request.cf
    const geoData = {
      colo: request.cf.colo || 'N/A',
      country: request.cf.country || 'N/A',
      city: request.cf.city || 'N/A',
      continent: request.cf.continent || 'N/A',
      latitude: request.cf.latitude || 'N/A',
      longitude: request.cf.longitude || 'N/A',
      postalCode: request.cf.postalCode || 'N/A',
      metroCode: request.cf.metroCode || 'N/A',
      region: request.cf.region || 'N/A',
      regionCode: request.cf.regionCode || 'N/A',
      timezone: request.cf.timezone || 'N/A'
    };

    return new Response(JSON.stringify(geoData), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    });
  }
};
</code></pre>
                            <button onclick="copyCode()" class="copy-button">Copia</button>
                        </div>
                    </li>

                    <li><strong>Imposta il Token Segreto nel Worker:</strong>
                        <ul>
                            <li>Assicurati di avere installato <em>Wrangler</em> (lo strumento CLI di Cloudflare).</li>
                            <li>Esegui il seguente comando per impostare la variabile segreta:</li>
                        </ul>
                        <pre><code>wrangler secret put SECRET_TOKEN</code></pre>
                        <ul>
                            <li>Quando richiesto, inserisci il valore del token segreto che vuoi utilizzare.</li>
                        </ul>
                    </li>

                    <li><strong>Distribuisci il Worker:</strong>
                        <ul>
                            <li>Salva e distribuisci il Worker dalla dashboard di Cloudflare.</li>
                            <li>Configura il Worker per essere eseguito sulle rotte desiderate del tuo sito web.</li>
                        </ul>
                    </li>
                </ol>

                <p>Una volta completata la configurazione, inserisci l'URL e il token del Worker nei campi sopra e salva
                    le impostazioni.</p>
            </div>
            <style>
                /* Stile per la box del codice */
                .code-container {
                    position: relative;
                    background-color: #f9f9f9;
                    padding: 15px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    font-family: monospace;
                    font-size: 14px;
                    color: #333;
                    white-space: pre-wrap;
                    overflow-x: auto;
                }

                /* Stile per il pulsante di copia */
                .copy-button {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    background-color: #0073aa;
                    color: #fff;
                    border: none;
                    padding: 5px 10px;
                    cursor: pointer;
                    border-radius: 3px;
                    font-size: 12px;
                }

                .copy-button:hover {
                    background-color: #005a87;
                }
            </style>

            <script>
                function copyCode() {
                    const code = document.getElementById("workerCode").textContent;
                    navigator.clipboard.writeText(code).then(() => {
                        const button = document.querySelector(".copy-button");
                        button.textContent = 'Copiato!';
                        setTimeout(() => button.textContent = 'Copia', 2000);
                    }).catch(err => console.error('Errore nella copia: ', err));
                }
            </script>
            <?php
        }


        /**
         * Effettua una chiamata al Worker Cloudflare per ottenere la geolocalizzazione.
         *
         * @return array Associative array contenente 'city' e 'region' se la chiamata ha successo, altrimenti errori.
         */
        public function get_location()
        {
            if (empty($this->worker_url) || empty($this->worker_token)) {
                return ['error' => 'Impostazioni del Worker non configurate correttamente.'];
            }

            $response = wp_remote_get($this->worker_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->worker_token,
                    'Content-Type' => 'application/json'
                ]
            ]);

            if (is_wp_error($response)) {
                return ['error' => $response->get_error_message()];
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE || empty($data['city']) || empty($data['region'])) {
                return ['error' => 'Dati di geolocalizzazione non validi o incompleti.'];
            }

            return [
                'city' => $data['city'],
            ];
        }
    }
}
