<?php
// Script temporaneo per cambiare città in Bitonto per autore 157 - CON BACKUP
// DA ELIMINARE DOPO L'USO

// Password di protezione
$password = 'temp123';

if (!isset($_GET['pass']) || $_GET['pass'] !== $password) {
    die('Accesso non autorizzato');
}

// Carica WordPress
require_once(dirname(__FILE__) . '/../../../wp-config.php');
global $wpdb;

$action = isset($_GET['action']) ? $_GET['action'] : 'preview';

if ($action === 'preview') {
    // STEP 1: PREVIEW - Mostra cosa cambierà
    $preview_sql = "
    SELECT p.ID, p.post_title, p.post_author, p.post_status,
           citta.meta_value as citta_attuale,
           prov.meta_value as provincia_attuale
    FROM wp_posts p
    INNER JOIN wp_postmeta citta ON p.ID = citta.post_id AND citta.meta_key = 'citta'
    LEFT JOIN wp_postmeta prov ON p.ID = prov.post_id AND prov.meta_key = 'provincia'
    WHERE p.post_type = 'annuncio-di-morte'
    AND p.post_author = 157
    AND citta.meta_value = 'Corato'
    ORDER BY p.ID
    LIMIT 100
    ";
    
    $results = $wpdb->get_results($preview_sql);
    
    // Conta totale
    $count_sql = "
    SELECT COUNT(p.ID) as totale
    FROM wp_posts p
    INNER JOIN wp_postmeta citta ON p.ID = citta.post_id AND citta.meta_key = 'citta'
    WHERE p.post_type = 'annuncio-di-morte'
    AND p.post_author = 157
    AND citta.meta_value = 'Corato'
    ";
    
    $totale = $wpdb->get_var($count_sql);
    
    echo "<h3>PREVIEW - Annunci autore 157 con Corato che diventeranno Bitonto:</h3>";
    echo "<p>Mostrando primi 100 di $totale annunci totali</p>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Titolo</th><th>Stato</th><th>Città Attuale</th><th>Provincia</th></tr>";
    
    foreach ($results as $row) {
        echo "<tr style='background-color: #ffeeee;'>";
        echo "<td>$row->ID</td>";
        echo "<td>" . substr($row->post_title, 0, 50) . "...</td>";
        echo "<td>$row->post_status</td>";
        echo "<td>$row->citta_attuale</td>";
        echo "<td>$row->provincia_attuale</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><strong>Totale annunci da modificare: $totale</strong>";
    echo "<br><strong>Filtri applicati:</strong>";
    echo "<br>• post_type = 'annuncio-di-morte'";
    echo "<br>• post_author = 157";
    echo "<br>• città = 'Corato'";
    echo "<br>• Tutti gli stati (draft, publish, etc.)";
    echo "<br><strong>Modifica:</strong> Corato → Bitonto";
    
    echo "<br><br><a href='?pass=temp123&action=backup' style='background: orange; color: white; padding: 10px; text-decoration: none;'>1. CREA BACKUP</a>";
    echo " → <a href='?pass=temp123&action=execute' style='background: red; color: white; padding: 10px; text-decoration: none;'>2. ESEGUI MODIFICA</a>";
    echo " → <a href='?pass=temp123&action=restore' style='background: blue; color: white; padding: 10px; text-decoration: none;'>3. RIPRISTINA (se necessario)</a>";
    
} elseif ($action === 'backup') {
    // STEP 2: BACKUP - Salva i dati originali
    $wpdb->query("DROP TABLE IF EXISTS wp_temp_bitonto_backup");
    
    $backup_sql = "
    CREATE TABLE wp_temp_bitonto_backup AS
    SELECT p.ID, p.post_author, 
           citta.meta_value as citta_old,
           prov.meta_value as provincia_old,
           NOW() as backup_time
    FROM wp_posts p
    INNER JOIN wp_postmeta citta ON p.ID = citta.post_id AND citta.meta_key = 'citta'
    LEFT JOIN wp_postmeta prov ON p.ID = prov.post_id AND prov.meta_key = 'provincia'
    WHERE p.post_type = 'annuncio-di-morte'
    AND p.post_author = 157
    AND citta.meta_value = 'Corato'
    ";
    
    $result = $wpdb->query($backup_sql);
    
    if ($result !== false) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM wp_temp_bitonto_backup");
        echo "<h3>✅ BACKUP CREATO</h3>";
        echo "Salvati $count record nella tabella wp_temp_bitonto_backup<br>";
        echo "Backup include: post_author, città originale, provincia originale<br><br>";
        echo "<a href='?pass=temp123&action=execute' style='background: red; color: white; padding: 10px; text-decoration: none;'>PROCEDI CON LA MODIFICA</a>";
    } else {
        echo "❌ ERRORE nel backup: " . $wpdb->last_error;
    }
    
} elseif ($action === 'execute') {
    // STEP 3: EXECUTE - Esegui la modifica
    
    // Aggiorna città da "Corato" a "Bitonto" per annunci autore 157
    $citta_sql = "
    UPDATE wp_postmeta citta
    INNER JOIN wp_posts p ON citta.post_id = p.ID
    SET citta.meta_value = 'Bitonto'
    WHERE p.post_type = 'annuncio-di-morte'
    AND p.post_author = 157
    AND citta.meta_key = 'citta'
    AND citta.meta_value = 'Corato'
    ";
    
    $citta_result = $wpdb->query($citta_sql);
    
    if ($citta_result !== false) {
        echo "<h3>✅ MODIFICA COMPLETATA</h3>";
        echo "• Città cambiate da Corato a Bitonto: $citta_result<br>";
        echo "<br>Criteri applicati:<br>";
        echo "• post_type = 'annuncio-di-morte'<br>";
        echo "• post_author = 157<br>";
        echo "• città = 'Corato'<br>";
        echo "• Tutti gli stati<br><br>";
        echo "Se qualcosa è andato storto:<br>";
        echo "<a href='?pass=temp123&action=restore' style='background: blue; color: white; padding: 10px; text-decoration: none;'>RIPRISTINA BACKUP</a>";
    } else {
        echo "❌ ERRORE nell'aggiornamento:<br>";
        echo "- Città: " . $wpdb->last_error . "<br>";
    }
    
} elseif ($action === 'restore') {
    // STEP 4: RESTORE - Ripristina dal backup
    if ($wpdb->get_var("SHOW TABLES LIKE 'wp_temp_bitonto_backup'")) {
        
        // Elimina tutte le città attuali per questi post
        $delete_citta = $wpdb->query("
            DELETE pm FROM wp_postmeta pm
            INNER JOIN wp_temp_bitonto_backup b ON pm.post_id = b.ID
            WHERE pm.meta_key = 'citta'
        ");
        
        // Reinserisci le città originali (solo se non erano NULL)
        $restore_citta = $wpdb->query("
            INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
            SELECT ID, 'citta', citta_old
            FROM wp_temp_bitonto_backup
            WHERE citta_old IS NOT NULL
        ");
        
        echo "<h3>✅ RIPRISTINO COMPLETATO</h3>";
        echo "• Città eliminate: $delete_citta<br>";
        echo "• Città ripristinate: $restore_citta<br><br>";
        echo "Backup eliminato automaticamente.";
        
        $wpdb->query("DROP TABLE wp_temp_bitonto_backup");
        
    } else {
        echo "❌ Backup non trovato!";
    }
}

echo "<br><br><a href='?pass=temp123'>← Torna al Preview</a>";
echo "<br><br><strong>RICORDA: Elimina questo file dopo l'uso!</strong>";
?>