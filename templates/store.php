<?php
/**
 * Custom Store Template for Dokan
 */

if (!defined('ABSPATH')) {
    exit;
}

$store_user = dokan()->vendor->get(get_query_var('author'));
$store_info = $store_user->get_shop_info();
$additional_info = get_user_meta($store_user->get_id(), 'dokan_additional_info', true);
$banner_url = $store_user->get_banner() ?: DOKAN_SELECT_PRODUCTS_PLUGIN_URL . 'assets/images/default-banner.jpg';

get_header('shop');
?>

    <style>

        :root {
            --my-primary: #486173;
            --my-secondary: #b7aa79;
            --my-gold: #dcbe52;
            --my-gray: #e9efee;

            --rad: .7rem;
            --dur: .3s;
            --color-dark: #2f2f2f;
            --color-light: #fff;
            /* --color-brand: #57bd84; */
            --font-fam: 'Lato', sans-serif;
            --height: 5rem;
            --btn-width: 6rem;
            --bez: cubic-bezier(0, 0, 0.43, 1.49);
        }

        .vendor-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            font-family: var(--font-fam);
        }

        .info-sidebar {
            background: var(--color-light);
            border-radius: var(--rad);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 0;
            overflow: hidden;
        }

        .vendor-logo {
            width: 100%;
            padding: 2rem;
            background: var(--my-gray);
            text-align: center;
        }

        .vendor-logo img {
            max-width: 200px;
            height: auto;
        }

        .info-section {
            padding: 1.5rem;
        }

        .info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
            padding: 0.75rem;
            border-bottom: 1px solid var(--my-gray);
        }

        .info-item i {
            margin-right: 1rem;
            color: var(--my-gold);
            width: 20px;
            margin-top: 3px;
        }

        .info-item span {
            flex: 1;
            color: var(--color-dark);
        }

        .main-content {
            background: var(--color-light);
            border-radius: var(--rad);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .section-title {
            color: var(--my-primary);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--my-gold);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--my-gold);
        }

        .store-description {
            font-size: 1.1rem;
            line-height: 1.8;
            color: var(--color-dark);
            margin-bottom: 2rem;
        }

        .map-container {
            margin-top: 2rem;
            border-radius: var(--rad);
            overflow: hidden;
            height: 400px;
            border: 1px solid var(--my-gray);
        }

        #osm-map {
            width: 100%;
            height: 100%;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .action-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--rad);
            border: none;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all var(--dur) var(--bez);
        }

        .button-primary {
            background: var(--my-gold);
            color: var(--color-dark);
        }

        .button-primary:hover {
            background: var(--my-secondary);
            color: var(--color-dark);
        }

        .button-secondary {
            background: var(--my-gray);
            color: var(--color-dark);
            border: 1px solid var(--my-primary);
        }

        .button-secondary:hover {
            background: var(--my-primary);
            color: var(--color-light);
        }

        @media (max-width: 768px) {
            .vendor-container {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="vendor-container">
        <div class="info-sidebar">
            <div class="vendor-logo">
                <!-- If vendor has a logo -->
                <img src="<?php echo esc_url($banner_url); ?>"
                     alt="<?php echo esc_attr($store_info['store_name']); ?>">
            </div>

            <div class="info-section">
                <div class="action-buttons">
                    <a href="tel:<?php echo esc_attr($store_info['phone']); ?>" class="action-button button-primary">
                        <i class="fas fa-phone"></i> Chiama
                    </a>
                    <a href="#map" class="action-button button-secondary">
                        <i class="fas fa-map-marker-alt"></i> Mappa
                    </a>
                </div>

                <ul class="info-list">
                    <?php if (!empty($store_info['address']['street_1'])): ?>
                        <li class="info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo esc_html($store_info['address']['street_1']); ?></span>
                        </li>
                    <?php endif; ?>

                    <?php if (!empty($store_info['address']['city'])): ?>
                        <li class="info-item">
                            <i class="fas fa-city"></i>
                            <span><?php echo esc_html($store_info['address']['city']); ?></span>
                        </li>
                    <?php endif; ?>

                    <?php if (!empty($store_info['phone'])): ?>
                        <li class="info-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo esc_html($store_info['phone']); ?></span>
                        </li>
                    <?php endif; ?>

                    <?php if (!empty($additional_info['phone_2'])): ?>
                        <li class="info-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo esc_html($additional_info['phone_2']); ?></span>
                        </li>
                    <?php endif; ?>

                    <?php if (!empty($additional_info['phone_3'])): ?>
                        <li class="info-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo esc_html($additional_info['phone_3']); ?></span>
                        </li>
                    <?php endif; ?>


                </ul>
            </div>
        </div>

        <div class="main-content">
            <h2 class="section-title">
                <i class="fas fa-store"></i> Chi siamo
            </h2>
            <?php if (!empty($additional_info['shop_description'])): ?>
                <div class="store-description">
                    <?php echo wp_kses_post($additional_info['shop_description']); ?>
                </div>
            <?php endif; ?>

            <h2 class="section-title" id="map">
                <i class="fas fa-map-marked-alt"></i> Dove siamo
            </h2>
            <div class="map-container">
                <div id="osm-map"></div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mapElement = document.getElementById('osm-map');

            if (mapElement) {
                const street = '<?php echo esc_js($store_info['address']['street_1']); ?>';
                const city = '<?php echo esc_js($store_info['address']['city']); ?>';
                const storeName = '<?php echo esc_js($store_info['store_name']); ?>';

                try {
                    const map = L.map('osm-map').setView([41.8719, 12.5674], 6);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(map);

                    const nominatimUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(street + ', ' + city + ', Italy')}`;
                    console.log('Calling URL:', nominatimUrl);

                    fetch(nominatimUrl)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Geocoding response data:', data);

                            if (data && data.length > 0) {
                                const lat = parseFloat(data[0].lat);
                                const lon = parseFloat(data[0].lon);

                                console.log('Coordinate trovate:', {lat, lon});

                                if (!isNaN(lat) && !isNaN(lon)) {
                                    map.setView([lat, lon], 16);

                                    const marker = L.marker([lat, lon])
                                        .addTo(map)
                                        .bindPopup(storeName);

                                    marker.openPopup();
                                } else {
                                    console.error('Coordinate non valide:', {lat, lon});
                                }
                            } else {
                                console.error('Nessun risultato trovato per l\'indirizzo:', street, city);
                                mapElement.innerHTML = '<p>Indirizzo non trovato</p>';
                            }
                        })
                        .catch(error => {
                            console.error('Errore durante la geolocalizzazione:', error);
                            mapElement.innerHTML = '<p>Errore durante il caricamento della mappa</p>';
                        });
                } catch (error) {
                    console.error('Errore nell\'inizializzazione della mappa:', error);
                    mapElement.innerHTML = '<p>Errore nell\'inizializzazionedellamappa < /p>';
                }
            }
        });
    </script>

<?php get_footer('shop'); ?>