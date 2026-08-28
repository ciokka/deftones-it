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

// ---------------------------------------------------------- anteprima
// Serve a leggere l'articolo intero prima di decidere: sull'archivio,
// dove i pezzi sono lunghi migliaia di caratteri, il sommario non basta.
if (preg_match('#^anteprima/(\d+)$#', $azione, $m)) {
    $q = $pdo->prepare('SELECT * FROM ' . t('articles') . ' WHERE id = ?');
    $q->execute([(int)$m[1]]);
    $a = $q->fetch();
    if (!$a) { pagina404(); }
    echo render('admin-anteprima', ['a' => $a],
        ['titolo' => 'Anteprima — ' . mb_substr($a['titolo_it'], 0, 60)]);
    exit;
}

// ---------------------------------------------------------- raccolte
if ($azione === 'raccolte') {
    $msg = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValido($_POST['csrf'] ?? null)) {
        $id = (int)($_POST['id'] ?? 0);
        $che = (string)($_POST['che'] ?? '');
        if (in_array($che, ['pubblica', 'ritira'], true)) {
            $pdo->prepare('UPDATE ' . t('temi') . ' SET stato = ? WHERE id = ?')
                ->execute([$che === 'pubblica' ? 'pubblicato' : 'draft', $id]);
            cacheSvuota();
            $msg = ['ok', $che === 'pubblica' ? 'Raccolta pubblicata.' : 'Raccolta ritirata.'];
        }
    }

    // Il conteggio distingue gli articoli pubblicati dal totale: una
    // raccolta con venti articoli tutti in bozza online sarebbe vuota.
    $raccolte = $pdo->query(
        'SELECT t.*,
                COUNT(a.id) AS totali,
                SUM(a.stato = \'pubblicato\') AS online
           FROM ' . t('temi') . ' t
           LEFT JOIN ' . t('articles') . ' a ON a.tema_id = t.id
          GROUP BY t.id ORDER BY t.ordine'
    )->fetchAll();

    echo render('admin-raccolte', ['raccolte' => $raccolte, 'messaggio' => $msg],
        ['titolo' => 'Raccolte — pannello']);
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
        } elseif ($che === 'multi') {
            // Azione su una selezione esplicita: nessuna pubblicazione
            // in blocco alla cieca, gli id li scegli tu con le caselle.
            $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
            $come = (string)($_POST['come'] ?? '');
            if ($ids && in_array($come, ['pubblica', 'scarta'], true)) {
                $segni = implode(',', array_fill(0, count($ids), '?'));
                if ($come === 'pubblica') {
                    $pdo->prepare('UPDATE ' . t('articles') . "
                                      SET stato = 'pubblicato',
                                          pubblicato_il = COALESCE(pubblicato_il, NOW())
                                    WHERE id IN ($segni) AND stato = 'draft'")->execute($ids);
                    cacheSvuota();
                    $messaggio = ['ok', count($ids) . ' articoli pubblicati.'];
                } else {
                    $pdo->prepare('UPDATE ' . t('articles') . "
                                      SET stato = 'scartato'
                                    WHERE id IN ($segni)")->execute($ids);
                    $messaggio = ['ok', count($ids) . ' bozze scartate.'];
                }
            } else {
                $messaggio = ['ko', 'Nessun articolo selezionato.'];
            }
        } elseif ($che === 'svuota') {
            $n = cacheSvuota();
            $messaggio = ['ok', "Cache svuotata ($n pagine)."];
        }
    }
}

// ------------------------------------------------------------- elenco
//
// Dopo l'importazione dell'archivio le bozze sono quasi seicento: senza
// filtri e paginazione la pagina sarebbe inutilizzabile, e senza ordine
// per lunghezza non troveresti mai i venti articoli che valgono davvero.

$perPagina = 25;
$pagina    = max(1, (int)($_GET['p'] ?? 1));
$cerca     = trim((string)($_GET['q'] ?? ''));
$anno      = (int)($_GET['anno'] ?? 0);
$cat       = (string)($_GET['cat'] ?? '');
$ordine    = (string)($_GET['ord'] ?? 'rilevanza');

$dove = ["stato = 'draft'"];
$par  = [];
if ($cerca !== '') {
    $dove[] = '(titolo_it LIKE ? OR sommario_it LIKE ?)';
    $par[] = '%' . $cerca . '%';
    $par[] = '%' . $cerca . '%';
}
if ($anno > 0)   { $dove[] = 'YEAR(pubblicato_il) = ?'; $par[] = $anno; }
if ($cat !== '') { $dove[] = 'categoria = ?';           $par[] = $cat; }
$filtro = implode(' AND ', $dove);

$ordinamenti = [
    'rilevanza' => 'rilevanza DESC, pubblicato_il DESC',
    'lunghi'    => 'CHAR_LENGTH(COALESCE(corpo_it, sommario_it)) DESC',
    'recenti'   => 'pubblicato_il DESC',
    'vecchi'    => 'pubblicato_il ASC',
];
$orderBy = $ordinamenti[$ordine] ?? $ordinamenti['rilevanza'];

$q = $pdo->prepare('SELECT COUNT(*) FROM ' . t('articles') . " WHERE $filtro");
$q->execute($par);
$totale = (int)$q->fetchColumn();
$pagine = max(1, (int)ceil($totale / $perPagina));
$pagina = min($pagina, $pagine);

$q = $pdo->prepare('SELECT id, slug, titolo_it, sommario_it, categoria, attendibilita,
                           rilevanza, fonte_nome, fonte_url, creato_il, pubblicato_il,
                           CHAR_LENGTH(COALESCE(corpo_it, \'\')) AS lunghezza
                      FROM ' . t('articles') . "
                     WHERE $filtro ORDER BY $orderBy
                     LIMIT $perPagina OFFSET " . (($pagina - 1) * $perPagina));
$q->execute($par);
$bozze = $q->fetchAll();

// per i menu a tendina dei filtri
$anni = $pdo->query('SELECT YEAR(pubblicato_il) AS a, COUNT(*) AS n
                       FROM ' . t('articles') . " WHERE stato = 'draft'
                      GROUP BY a ORDER BY a DESC")->fetchAll();
$categorie = $pdo->query('SELECT categoria, COUNT(*) AS n
                            FROM ' . t('articles') . " WHERE stato = 'draft'
                           GROUP BY categoria ORDER BY n DESC")->fetchAll();

$pubblicate = $pdo->query('SELECT id, slug, titolo_it, pubblicato_il, fonte_nome
                             FROM ' . t('articles') . '
                            WHERE stato = \'pubblicato\'
                            ORDER BY pubblicato_il DESC LIMIT 15')->fetchAll();

$ultimo = $pdo->query('SELECT job, finito_il, esito, item_elaborati, messaggio
                         FROM ' . t('run_log') . '
                        ORDER BY id DESC LIMIT 5')->fetchAll();

echo render('admin-bozze', [
    'bozze' => $bozze, 'pubblicate' => $pubblicate, 'ultimo' => $ultimo,
    'messaggio' => $messaggio, 'totale' => $totale, 'pagina' => $pagina,
    'pagine' => $pagine, 'cerca' => $cerca, 'anno' => $anno, 'cat' => $cat,
    'ordine' => $ordine, 'anni' => $anni, 'categorie' => $categorie,
], ['titolo' => 'Pannello — deftones.it']);
