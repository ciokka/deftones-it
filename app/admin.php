<?php
/**
 * admin.php — il pannello. Tre pagine: primo accesso, login, bozze.
 * Ci si arriva solo da index.php, che ha già caricato bootstrap e web.
 */
declare(strict_types=1);

sessioneAvvia();
$pdo = db();
$azione = substr($percorso, strlen('/admin'));
$azione = trim($azione, '/');

$quantiAdmin = (int)$pdo->query('SELECT COUNT(*) FROM ' . t('admin_users'))->fetchColumn();

// ------------------------------------------------- primo accesso
// Funziona solo finché non esiste nessun utente: dopo si disattiva da sé,
// quindi non resta una porta aperta se ti dimentichi di cancellarlo.
if ($quantiAdmin === 0) {
    $errore = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ut = trim((string)($_POST['utente'] ?? ''));
        $pw = (string)($_POST['password'] ?? '');
        if (mb_strlen($ut) < 3)  { $errore = 'Nome utente troppo corto.'; }
        elseif (mb_strlen($pw) < 10) { $errore = 'La password deve avere almeno 10 caratteri.'; }
        else {
            $pdo->prepare('INSERT INTO ' . t('admin_users') . ' (username, password_hash) VALUES (?,?)')
                ->execute([$ut, password_hash($pw, PASSWORD_DEFAULT)]);
            $_SESSION['admin_id'] = (int)$pdo->lastInsertId();
            vaiA('admin/');
        }
    }
    echo render('admin-primo', ['errore' => $errore], ['titolo' => 'Primo accesso']);
    exit;
}

// ------------------------------------------------------------- uscita
if ($azione === 'esci') {
    $_SESSION = [];
    session_destroy();
    vaiA('admin/');
}

// -------------------------------------------------------------- login
if (!loggato()) {
    $errore = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $q = $pdo->prepare('SELECT id, password_hash FROM ' . t('admin_users') . ' WHERE username = ?');
        $q->execute([trim((string)($_POST['utente'] ?? ''))]);
        $u = $q->fetch();
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$u['id'];
            $pdo->prepare('UPDATE ' . t('admin_users') . ' SET ultimo_accesso = NOW() WHERE id = ?')
                ->execute([$u['id']]);
            vaiA('admin/');
        }
        // messaggio volutamente generico: non diciamo quale dei due è sbagliato
        $errore = 'Credenziali non valide.';
        usleep(400_000);
    }
    echo render('admin-accesso', ['errore' => $errore], ['titolo' => 'Accesso']);
    exit;
}

// ---------------------------------------------------- azioni sulle bozze
$messaggio = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $azione === 'azione') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $messaggio = ['ko', 'Sessione scaduta, riprova.'];
    } else {
        $id  = (int)($_POST['id'] ?? 0);
        $che = (string)($_POST['che'] ?? '');

        if ($che === 'pubblica') {
            $pdo->prepare('UPDATE ' . t('articles') . '
                              SET stato = \'pubblicato\', pubblicato_il = COALESCE(pubblicato_il, NOW())
                            WHERE id = ? AND stato = \'draft\'')->execute([$id]);
            $n = cacheSvuota();
            $messaggio = ['ok', "Pubblicata. Cache svuotata ($n pagine)."];
        } elseif ($che === 'scarta') {
            $pdo->prepare('UPDATE ' . t('articles') . ' SET stato = \'scartato\' WHERE id = ?')
                ->execute([$id]);
            $messaggio = ['ok', 'Bozza scartata.'];
        } elseif ($che === 'ritira') {
            $pdo->prepare('UPDATE ' . t('articles') . ' SET stato = \'draft\' WHERE id = ?')
                ->execute([$id]);
            cacheSvuota();
            $messaggio = ['ok', 'Ritirata dal sito, torna fra le bozze.'];
        } elseif ($che === 'svuota') {
            $n = cacheSvuota();
            $messaggio = ['ok', "Cache svuotata ($n pagine)."];
        }
    }
}

// ------------------------------------------------------------- elenco
$bozze = $pdo->query('SELECT * FROM ' . t('articles') . '
                       WHERE stato = \'draft\'
                       ORDER BY rilevanza DESC, creato_il DESC')->fetchAll();

$pubblicate = $pdo->query('SELECT id, slug, titolo_it, pubblicato_il, fonte_nome
                             FROM ' . t('articles') . '
                            WHERE stato = \'pubblicato\'
                            ORDER BY pubblicato_il DESC LIMIT 15')->fetchAll();

$ultimo = $pdo->query('SELECT job, finito_il, esito, item_elaborati, messaggio
                         FROM ' . t('run_log') . '
                        ORDER BY id DESC LIMIT 5')->fetchAll();

echo render('admin-bozze', [
    'bozze' => $bozze, 'pubblicate' => $pubblicate,
    'ultimo' => $ultimo, 'messaggio' => $messaggio,
], ['titolo' => 'Pannello — deftones.it']);
