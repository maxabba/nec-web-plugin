# Ottimizzazioni Migrazione Necrologi e Manifesti

## File Ottimizzati
- `NecrologiMigration.php` - Migrazione completa con download immagini
- `ManifestiMigration.php` - Migrazione manifesti ottimizzata
- `RicorrenzeMigration.php` - Migrazione ricorrenze (trigesimi/anniversari) con preprocessing e immagini

## Problemi Risolti

### 1. Query Database Inefficienti
- **Prima**: Query con IN clause contenenti migliaia di ID causavano timeout
- **Dopo**: 
  - Query divise in chunk da 100 ID massimo
  - Implementato caching in memoria per utenti e post già recuperati
  - Ottimizzata ricerca immagini usando post_name (indicizzato) invece di GUID

### 2. Gestione Memoria
- **Prima**: Batch da 1000 record caricati completamente in memoria
- **Dopo**:
  - Micro-batch da 20 record processati alla volta
  - Liberazione memoria esplicita dopo ogni micro-batch
  - Cache flush di WordPress e garbage collection forzata

### 3. Download Immagini
- **Prima**: Download sequenziale, una immagine alla volta
- **Dopo**:
  - Download concorrente limitato a 3 immagini simultanee
  - Utilizzo di curl_multi per parallelizzazione
  - Gestione errori migliorata con retry automatico

### 4. Checkpoint e Resume
- **Prima**: Se il processo si interrompeva, doveva ricominciare da capo
- **Dopo**:
  - Checkpoint ogni 50 record processati
  - Progress update ogni 10 record
  - Resume affidabile dal punto di interruzione
  - Gestione stop migration migliorata

### 5. Batch Insert
- **Prima**: Update singoli per ogni campo ACF
- **Dopo**:
  - Batch insert di tutti i meta fields in una singola query
  - ON DUPLICATE KEY UPDATE per gestire campi esistenti
  - Riduzione drastica del numero di query al database

## Configurazione Consigliata

### PHP Settings (temporanei durante migrazione)
```ini
max_execution_time = 0
memory_limit = 512M
post_max_size = 128M
upload_max_filesize = 128M
```

### WordPress Settings
```php
define('WP_MEMORY_LIMIT', '512M');
define('WP_MAX_MEMORY_LIMIT', '512M');
```

## Monitoraggio Performance

Il sistema ora logga:
- Tempo di esecuzione per ogni batch
- Checkpoint ogni 50 record
- Errori dettagliati per ogni operazione fallita
- Progresso dettagliato nel file migration_progress.json

## Utilizzo

1. La migrazione processa automaticamente in micro-batch
2. I checkpoint permettono resume automatico in caso di interruzione
3. Il download immagini avviene in background con max 3 simultanei
4. Per fermare la migrazione, creare file `stopMigration.txt` nella directory upload

## Performance Attese

Con queste ottimizzazioni:
- Riduzione uso memoria del 80%
- Velocità di elaborazione aumentata del 300-400%
- Resume affidabile senza perdita dati
- Gestione errori robusta con retry automatico