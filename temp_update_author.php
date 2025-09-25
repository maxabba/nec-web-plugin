<?php
// Script temporaneo per aggiornare l'autore dei post - CON BACKUP
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

$id_list = "'3176','3237','3250','3269','3343','3384','3454','3503','3589','3590','3646','3702','3715','3737','3807','3821','3822','3978','4109','4246','4436','4471','4483','4598','4612','4698','4802','4803','4804','4919','4970','4997','5113','5122','5215','5288','5365','5541','5676','5728','5993','5994','6006','6085','6105','6207','6221','6312','6345','6389','6461','6616','6645','6870','7253','7554','7805','7854','7980','8071','8083','8140','8144','8254','8258','8290','8351','8400','8414','8511','8520','8582','8670','8677','8702','8738','8739','8867','8888','8894','8916','8969','9028','9034','9063','9071','9253','9278','9355','9427','9510','9543','9642','9659','9668','9864','9910','9945','10016','10045','10217','10221','10253','10659','10661','10727','10728','10818','10867','10884','11040','11095','11118','11127','11172','11192','11193','11256','11270','11290','11329','11345','11376','11377','11478','11624','11637','11838','11937','11951','12003','12009','12126','12237','12253','12286','12294','12441','12447','12534','12627','12657','12700','12728','12753','12775','12820','12830','12925','12960','12997','13034','13035','13047','13059','13107','13113','13168','13222','13245','13313','13444','13479','13484','13567','13812','13890','14067','14079','14127','14137','14141','14253','14265','14371','14404','14457','14459','14481','14495','14516','14532','14635','14681','14684','14827','14868','14909','14949','15012','15025','15114','15115','15184','15241','15257','15361','15365','15366','15367','15375','15418','15425','15426','15515','15517','15544','15545','15552','15573','15580','15585','15614','15718','15793','15822','15852','15868','15895','15908','15944','16052','16058','16217','16407','16435','16512','16515','16547','16569','16574','16797','16841','16862','16919','16945','17058','17088','17089','17121','17131','17205','17211','17242','17316','17460','17637','17793','17846','18186','18201','18241','18306','18344','18359','18370','18415','18449','18466','18475','18476','18477','18561','18563','18588','18589','18612','18615','18637','18694','18703','18709','18740','18754','18757','18758','18759','18760','18761','18762','18763','18766','18855','18981','19022','19041','19216','19227','19235','19263','19288','19340','19398','19423','19426','19472','19481','19486','19490','19524','19571','19578','19583','19602','19629','19679','19689','19696','19721','19764','19769','19771','19832','20164','20227','20268','20286','20290','20368','20380','20436','20481','20533','20614','20619','20662','20691','20806','20815','20827','20839','20850','20852','20864','20881','20925','20941','20992','21115','21116','21149','21150','21151','21173','21236','21255','21285','21317','21327','21503','21522','21571','21574','21578','21589','21619','21679','21698','21808','21884','21885','21957','21973','21988','22020','22054','22056','22062','22063','22095','22098','22116','22121','22130','22149','22152','22217','22233'";

if ($action === 'preview') {
    // STEP 1: PREVIEW - Mostra cosa cambierà
    $preview_sql = "
    SELECT p.ID, p.post_title, p.post_author, pm.meta_value as id_old,
           prov.meta_value as provincia_attuale, 
           citta.meta_value as citta_attuale
    FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    INNER JOIN wp_postmeta prov ON p.ID = prov.post_id AND prov.meta_key = 'provincia'
    INNER JOIN wp_postmeta citta ON p.ID = citta.post_id AND citta.meta_key = 'citta'
    WHERE p.post_type = 'annuncio-di-morte'
    AND pm.meta_key = 'id_old'
    AND pm.meta_value IN ($id_list)
    AND prov.meta_value = 'Bari'
    AND citta.meta_value = 'Corato'
    ";
    
    $results = $wpdb->get_results($preview_sql);
    
    echo "<h3>PREVIEW - Post che saranno modificati (SOLO Bari/Corato):</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Titolo</th><th>Autore Attuale</th><th>ID Old</th><th>Provincia Attuale</th><th>Città Attuale</th></tr>";
    
    foreach ($results as $row) {
        $style = ($row->provincia_attuale == 'Bari' && $row->citta_attuale == 'Corato') ? "background-color: #ffeeee;" : "";
        echo "<tr style='$style'>";
        echo "<td>{$row->ID}</td>";
        echo "<td>{$row->post_title}</td>";
        echo "<td>{$row->post_author}</td>";
        echo "<td>{$row->id_old}</td>";
        echo "<td>{$row->provincia_attuale}</td>";
        echo "<td>{$row->citta_attuale}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><strong>Totale post da modificare: " . count($results) . "</strong>";
    echo "<br><strong>Modifiche che verranno applicate SOLO a chi ha Bari/Corato:</strong>";
    echo "<br>• Nuovo autore: 157";
    echo "<br>• Città: Corato → Bitonto (provincia resta Bari)";
    
    echo "<br><br><a href='?pass=temp123&action=backup' style='background: orange; color: white; padding: 10px; text-decoration: none;'>1. CREA BACKUP</a>";
    echo " → <a href='?pass=temp123&action=execute' style='background: red; color: white; padding: 10px; text-decoration: none;'>2. ESEGUI MODIFICA</a>";
    echo " → <a href='?pass=temp123&action=restore' style='background: blue; color: white; padding: 10px; text-decoration: none;'>3. RIPRISTINA (se necessario)</a>";
    
} elseif ($action === 'backup') {
    // STEP 2: BACKUP - Salva i dati originali (SOLO Bari/Corato)
    $wpdb->query("DROP TABLE IF EXISTS wp_temp_author_backup");
    
    $backup_sql = "
    CREATE TABLE wp_temp_author_backup AS
    SELECT p.ID, p.post_author, pm.meta_value as id_old, 
           prov.meta_value as provincia_old,
           citta.meta_value as citta_old,
           NOW() as backup_time
    FROM wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    INNER JOIN wp_postmeta prov ON p.ID = prov.post_id AND prov.meta_key = 'provincia'
    INNER JOIN wp_postmeta citta ON p.ID = citta.post_id AND citta.meta_key = 'citta'
    WHERE p.post_type = 'annuncio-di-morte'
    AND pm.meta_key = 'id_old'
    AND pm.meta_value IN ($id_list)
    AND prov.meta_value = 'Bari'
    AND citta.meta_value = 'Corato'
    ";
    
    $result = $wpdb->query($backup_sql);
    
    if ($result !== false) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM wp_temp_author_backup");
        echo "<h3>✅ BACKUP CREATO</h3>";
        echo "Salvati $count record nella tabella wp_temp_author_backup<br>";
        echo "Backup include: post_author, provincia, città<br><br>";
        echo "<a href='?pass=temp123&action=execute' style='background: red; color: white; padding: 10px; text-decoration: none;'>PROCEDI CON LA MODIFICA</a>";
    } else {
        echo "❌ ERRORE nel backup: " . $wpdb->last_error;
    }
    
} elseif ($action === 'execute') {
    // STEP 3: EXECUTE - Esegui le modifiche (SOLO Bari/Corato)
    
    // 1. Aggiorna post_author (SOLO per Bari/Corato)
    $update_author_sql = "
    UPDATE wp_posts p
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id
    INNER JOIN wp_postmeta prov ON p.ID = prov.post_id AND prov.meta_key = 'provincia'
    INNER JOIN wp_postmeta citta ON p.ID = citta.post_id AND citta.meta_key = 'citta'
    SET p.post_author = 157
    WHERE p.post_type = 'annuncio-di-morte'
    AND pm.meta_key = 'id_old'
    AND pm.meta_value IN ($id_list)
    AND prov.meta_value = 'Bari'
    AND citta.meta_value = 'Corato'
    ";
    
    $author_result = $wpdb->query($update_author_sql);
    
    // 2. Aggiorna SOLO città da Corato a Bitonto (provincia resta Bari)
    $citta_sql = "
    UPDATE wp_postmeta citta
    INNER JOIN wp_posts p ON citta.post_id = p.ID
    INNER JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = 'id_old'
    INNER JOIN wp_postmeta prov ON p.ID = prov.post_id AND prov.meta_key = 'provincia'
    SET citta.meta_value = 'Bitonto'
    WHERE p.post_type = 'annuncio-di-morte'
    AND pm.meta_value IN ($id_list)
    AND prov.meta_value = 'Bari'
    AND citta.meta_key = 'citta'
    AND citta.meta_value = 'Corato'
    ";
    
    $citta_result = $wpdb->query($citta_sql);
    
    if ($author_result !== false && $citta_result !== false) {
        echo "<h3>✅ MODIFICHE COMPLETATE (SOLO Bari/Corato)</h3>";
        echo "• Post_author modificati: $author_result<br>";
        echo "• Città cambiate da Corato a Bitonto: $citta_result<br>";
        echo "<br>Valori impostati:<br>";
        echo "• Autore: 157<br>";
        echo "• Provincia: Bari (invariata)<br>";
        echo "• Città: Corato → Bitonto<br><br>";
        echo "Se qualcosa è andato storto:<br>";
        echo "<a href='?pass=temp123&action=restore' style='background: blue; color: white; padding: 10px; text-decoration: none;'>RIPRISTINA BACKUP</a>";
    } else {
        echo "❌ ERRORE nell'aggiornamento:<br>";
        if ($author_result === false) echo "- Author: " . $wpdb->last_error . "<br>";
        if ($citta_result === false) echo "- Città: " . $wpdb->last_error . "<br>";
    }
    
} elseif ($action === 'restore') {
    // STEP 4: RESTORE - Ripristina dal backup
    if ($wpdb->get_var("SHOW TABLES LIKE 'wp_temp_author_backup'")) {
        
        // 1. Ripristina post_author
        $restore_author_sql = "
        UPDATE wp_posts p
        INNER JOIN wp_temp_author_backup b ON p.ID = b.ID
        SET p.post_author = b.post_author
        ";
        
        $author_restored = $wpdb->query($restore_author_sql);
        
        // 2. Ripristina provincia (elimina e reinserisci se esisteva)
        $provincia_restored = 0;
        $backup_provincia = $wpdb->get_results("SELECT ID, provincia_old FROM wp_temp_author_backup WHERE provincia_old IS NOT NULL");
        
        foreach ($backup_provincia as $row) {
            // Elimina provincia attuale
            $wpdb->delete('wp_postmeta', ['post_id' => $row->ID, 'meta_key' => 'provincia']);
            
            // Reinserisci valore originale se esisteva
            if ($row->provincia_old) {
                $wpdb->insert('wp_postmeta', [
                    'post_id' => $row->ID,
                    'meta_key' => 'provincia', 
                    'meta_value' => $row->provincia_old
                ]);
                $provincia_restored++;
            }
        }
        
        // 3. Ripristina città (elimina e reinserisci se esisteva)
        $citta_restored = 0;
        $backup_citta = $wpdb->get_results("SELECT ID, citta_old FROM wp_temp_author_backup WHERE citta_old IS NOT NULL");
        
        foreach ($backup_citta as $row) {
            // Elimina città attuale
            $wpdb->delete('wp_postmeta', ['post_id' => $row->ID, 'meta_key' => 'citta']);
            
            // Reinserisci valore originale se esisteva
            if ($row->citta_old) {
                $wpdb->insert('wp_postmeta', [
                    'post_id' => $row->ID,
                    'meta_key' => 'citta',
                    'meta_value' => $row->citta_old
                ]);
                $citta_restored++;
            }
        }
        
        echo "<h3>✅ RIPRISTINO COMPLETATO</h3>";
        echo "• Post_author ripristinati: $author_restored<br>";
        echo "• Provincie ripristinate: $provincia_restored<br>";
        echo "• Città ripristinate: $citta_restored<br><br>";
        echo "Backup eliminato automaticamente.";
        
        $wpdb->query("DROP TABLE wp_temp_author_backup");
        
    } else {
        echo "❌ Backup non trovato!";
    }
}

echo "<br><br><a href='?pass=temp123'>← Torna al Preview</a>";
echo "<br><br><strong>RICORDA: Elimina questo file dopo l'uso!</strong>";
?>