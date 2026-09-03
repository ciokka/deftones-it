<?php
/**
 * admin.php — il pannello. Tre pagine: primo accesso, login, bozze.
 * Ci si arriva solo da index.php, che ha già caricato bootstrap e web.
 */
declare(strict_types=1);

// Le iconcine servono a tutte le viste del pannello, compresa
// l'anteprima che viene resa poche righe più sotto: la richiesta va
// quindi qui e non a metà file.
require_once __DIR__ . '/lib/icone.php';

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

// ---------------------------------------------------------- richieste
if ($azione === 'richieste') {
    $msg = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValido($_POST['csrf'] ?? null)) {
        $che = (string)($_POST['che'] ?? '');
        if ($che === 'nuova') {
            $testo = trim((string)($_POST['richiesta'] ?? ''));
            if (mb_strlen($testo) < 10) {
                $msg = ['ko', 'Scrivi almeno una frase: "la strumentazione di Stephen Carpenter" '
                            . 'produce un articolo migliore di "Steph".'];
            } else {
                $pdo->prepare('INSERT INTO ' . t('richieste') . ' (richiesta, indicazioni) VALUES (?,?)')
                    ->execute([mb_substr($testo, 0, 500),
                               trim((string)($_POST['indicazioni'] ?? '')) ?: null]);
                $msg = ['ok', 'Richiesta in coda. Verrà scritta al prossimo giro.'];
            }
        } elseif ($che === 'rifai') {
            $pdo->prepare('UPDATE ' . t('richieste') . "
                              SET stato = 'attesa', nota = NULL WHERE id = ?")
                ->execute([(int)($_POST['id'] ?? 0)]);
            $msg = ['ok', 'Rimessa in coda.'];
        } elseif ($che === 'elimina') {
            $pdo->prepare('DELETE FROM ' . t('richieste') . ' WHERE id = ?')
                ->execute([(int)($_POST['id'] ?? 0)]);
            $msg = ['ok', 'Richiesta eliminata. La bozza eventualmente prodotta resta.'];
        }
    }

    $richieste = $pdo->query('SELECT r.*, a.slug, a.titolo_it, a.stato AS stato_articolo
                                FROM ' . t('richieste') . ' r
                                LEFT JOIN ' . t('articles') . ' a ON a.id = r.articolo_id
                               ORDER BY r.creato_il DESC LIMIT 50')->fetchAll();

    echo render('admin-richieste', ['richieste' => $richieste, 'messaggio' => $msg],
        ['titolo' => 'Richieste — pannello']);
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
        } elseif ($che === 'pubblica-copertina') {
            // Pubblica e illustra in un gesto solo. Senza, l'articolo
            // restava spaiato fino al giro delle copertine, che passa
            // ogni quattro ore: proprio nel momento in cui lo si va a
            // guardare, appena pubblicato.
            $pdo->prepare('UPDATE ' . t('articles') . '
                              SET stato = \'pubblicato\', pubblicato_il = COALESCE(pubblicato_il, NOW())
                            WHERE id = ? AND stato = \'draft\'')->execute([$id]);

            require_once __DIR__ . '/lib/copertine.php';
            $q = $pdo->prepare('SELECT id, slug, titolo_it, tag, immagine_url
                                  FROM ' . t('articles') . ' WHERE id = ?');
            $q->execute([$id]);
            $art = $q->fetch();

            if (!$art) {
                $nota = 'articolo non trovato';
            } elseif (!empty($art['immagine_url'])) {
                $nota = 'aveva già una copertina';
            } else {
                // Qui si scarica un file: qualche secondo di attesa è
                // normale, ed è il motivo per cui questo pulsante è
                // separato da "pubblica" invece di sostituirlo.
                $r = assegnaCopertina($pdo, $art, dischiPerTitolo($pdo));
                $nota = match ($r['origine']) {
                    'disco'   => 'copertina di ' . $r['nota'],
                    'commons' => 'foto di ' . $r['nota'],
                    default   => 'nessuna foto adatta trovata',
                };
            }

            $n = cacheSvuota();
            $messaggio = ['ok', "Pubblicata — $nota. Cache svuotata ($n pagine)."];
        } elseif ($che === 'scarta') {
            $pdo->prepare('UPDATE ' . t('articles') . ' SET stato = \'scartato\' WHERE id = ?')
                ->execute([$id]);
            $messaggio = ['ok', 'Bozza scartata.'];
        } elseif ($che === 'apertura') {
            // Un interruttore, non due azioni: il pulsante dice sempre
            // il contrario di com'è adesso, e premerlo lo rovescia.
            $pdo->prepare('UPDATE ' . t('articles') . '
                              SET in_apertura = 1 - in_apertura WHERE id = ?')->execute([$id]);
            $q = $pdo->prepare('SELECT in_apertura FROM ' . t('articles') . ' WHERE id = ?');
            $q->execute([$id]);
            cacheSvuota();
            $messaggio = ['ok', $q->fetchColumn()
                ? 'Fissata in apertura: resta nel carosello anche quando escono notizie nuove.'
                : 'Tolta dall\'apertura: torna a contare solo la data.'];
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
        } elseif ($che === 'copertina-no') {
            // Due cose insieme, perché è quello che serve davvero: la
            // foto sbagliata sparisce da questo articolo *e* dal
            // catalogo, così il prossimo giro non te la ripropone su un
            // altro pezzo. E l'articolo torna in coda, così al giro
            // successivo ne cerca un'altra.
            $q = $pdo->prepare('SELECT slug, immagine_fonte_url, immagine_origine
                                  FROM ' . t('articles') . ' WHERE id = ?');
            $q->execute([$id]);
            $r = $q->fetch();

            if ($r && $r['immagine_origine'] === 'commons' && $r['immagine_fonte_url']) {
                $pdo->prepare('UPDATE ' . t('immagini') . '
                                  SET scartata = 1 WHERE url_pagina = ?')
                    ->execute([$r['immagine_fonte_url']]);
            }
            if ($r) {
                $f = rtrim((string)(cfg('media_dir') ?: '/home/bpdefton/public_html/media'), '/')
                   . '/copertine/' . $r['slug'] . '.jpg';
                if (is_file($f)) { @unlink($f); }
            }

            $pdo->prepare('UPDATE ' . t('articles') . '
                              SET immagine_url = NULL, immagine_autore = NULL,
                                  immagine_licenza = NULL, immagine_licenza_url = NULL,
                                  immagine_fonte_url = NULL, immagine_origine = NULL,
                                  immagine_cercata_il = NULL
                            WHERE id = ?')->execute([$id]);
            cacheSvuota();
            $messaggio = ['ok', 'Copertina rifiutata: quella foto non verrà più scelta, '
                              . 'e al prossimo giro l\'articolo ne prende un\'altra.'];
        } elseif ($che === 'svuota') {
            $n = cacheSvuota();
            $messaggio = ['ok', "Cache svuotata ($n pagine)."];
        }
    }
}

/**
 * Il messaggio che sopravvive a un redirect.
 *
 * Dopo aver salvato si rimanda a un'altra pagina — o un aggiornamento
 * del browser rifarebbe il salvataggio — e la variabile con l'esito si
 * perderebbe per strada. Si posa in sessione e si raccoglie di là, una
 * volta sola.
 */
function messaggioDiPassaggio(): ?array
{
    if (empty($_SESSION['messaggio'])) { return null; }
    $m = $_SESSION['messaggio'];
    unset($_SESSION['messaggio']);
    return is_array($m) ? $m : null;
}

// --------------------------------------------- catalogo fotografie

if ($azione === 'foto') {
    require_once __DIR__ . '/lib/copertine.php';
    $msg = messaggioDiPassaggio();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValido($_POST['csrf'] ?? null)) {
        if (!empty($_POST['raccolta'])) {
            $_SESSION['messaggio'] = lanciaRaccolta((string)$_POST['raccolta']);
        } else {
            // Un interruttore solo: la fotografia va o non va bene. Rovesciarlo
            // non cancella niente — le scartate restano, così una raccolta
            // futura non le riporta dentro e un ripensamento costa un clic.
            $pdo->prepare('UPDATE ' . t('immagini') . '
                 SET scartata = 1 - scartata WHERE id = ?')->execute([(int)($_POST['img'] ?? 0)]);
        }
        vaiA('admin/foto' . (string)($_POST['torna'] ?? ''));
    }

    $stato = (string)($_GET['stato'] ?? 'attive');
    $sog   = (string)($_GET['sog'] ?? '');
    $cerca = trim((string)($_GET['q'] ?? ''));

    $dove = [];
    $par  = [];
    if ($stato === 'attive')   { $dove[] = 'scartata = 0'; }
    if ($stato === 'scartate') { $dove[] = 'scartata = 1'; }
    if ($sog !== '')   { $dove[] = 'soggetto = ?';  $par[] = $sog; }
    if ($cerca !== '') { $dove[] = '(autore LIKE ? OR riferimento LIKE ?)';
                         $par[] = '%' . $cerca . '%'; $par[] = '%' . $cerca . '%'; }
    $filtro = $dove ? 'WHERE ' . implode(' AND ', $dove) : '';

    // Le ultime arrivate sono quelle da guardare: una raccolta ne porta
    // centosettanta in un colpo e vanno passate in rassegna, mentre le
    // altre stanno lì da settimane e le hai già viste. Con
    // l'ordinamento consueto finivano oltre la quattrocentesima riga,
    // cioè fuori dalla pagina.
    $ord = ((string)($_GET['ord'] ?? '')) === 'recenti'
         ? 'id DESC'
         : 'scartata, soggetto, usata DESC, id';
    $q = $pdo->prepare('SELECT * FROM ' . t('immagini') . "
                         $filtro ORDER BY $ord LIMIT 400");
    $q->execute($par);
    $foto = $q->fetchAll();

    $conti = $pdo->query('SELECT
            COUNT(*) AS tutte,
            SUM(scartata = 0) AS attive,
            SUM(scartata = 1) AS scartate
          FROM ' . t('immagini'))->fetch();
    $soggetti = $pdo->query('SELECT soggetto, COUNT(*) AS n FROM ' . t('immagini') . '
                              GROUP BY soggetto ORDER BY n DESC')->fetchAll();

    echo render('admin-foto', [
        'foto' => $foto, 'conti' => $conti, 'soggetti' => $soggetti,
        'stato' => $stato, 'sog' => $sog, 'cerca' => $cerca, 'messaggio' => $msg,
        'log' => codaLog(),
    ], ['titolo' => 'Catalogo fotografie — pannello']);
    exit;
}

// ------------------------------------------------ scelta copertina

if (preg_match('#^copertina/(\d+)$#', $azione, $m)) {
    require_once __DIR__ . '/lib/copertine.php';
    $id  = (int)$m[1];
    $msg = messaggioDiPassaggio();

    $q = $pdo->prepare('SELECT * FROM ' . t('articles') . ' WHERE id = ? LIMIT 1');
    $q->execute([$id]);
    $a = $q->fetch();
    if (!$a) { pagina404(); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValido($_POST['csrf'] ?? null)) {
        $che = (string)($_POST['che'] ?? '');

        if ($che === 'automatica') {
            // Rimette l'articolo in coda e rifà la scelta come farebbe il
            // cron, senza aspettare il giro delle quattro ore.
            $r = assegnaCopertina($pdo, $a, dischiPerTitolo($pdo));
            $_SESSION['messaggio'] = ['ok', match ($r['origine']) {
                'disco'   => 'Copertina di ' . $r['nota'] . '.',
                'commons' => 'Scelta una foto di ' . $r['nota'] . '.',
                default   => 'Nessuna foto adatta trovata.',
            }];

        } elseif ($che === 'scelta') {
            $q = $pdo->prepare('SELECT * FROM ' . t('immagini') . ' WHERE id = ? LIMIT 1');
            $q->execute([(int)($_POST['img'] ?? 0)]);
            $img = $q->fetch();

            if (!$img) {
                $_SESSION['messaggio'] = ['ko', 'Quella fotografia non è più nel catalogo.'];
            } else {
                $dati = httpGet((string)$img['url_file'])['body'] ?? null;
                $file = cartellaCopertine() . '/' . $a['slug'] . '.jpg';
                if ($dati === null || strlen($dati) < 5000
                    || @file_put_contents($file, $dati) === false) {
                    $_SESSION['messaggio'] = ['ko', 'Non sono riuscito a scaricarla. Riprova.'];
                } else {
                    // origine "manuale": il cron non la tocca più, nemmeno
                    // con --rifai. L'hai scelta tu.
                    $pdo->prepare('UPDATE ' . t('articles') . '
                         SET immagine_url = ?, immagine_autore = ?, immagine_licenza = ?,
                             immagine_licenza_url = ?, immagine_fonte_url = ?,
                             immagine_origine = \'manuale\', immagine_cercata_il = NOW()
                       WHERE id = ?')->execute([
                        '/media/copertine/' . $a['slug'] . '.jpg',
                        $img['autore'], $img['licenza'], $img['licenza_url'],
                        $img['url_pagina'], $id,
                    ]);
                    $pdo->prepare('UPDATE ' . t('immagini') . '
                         SET usata = usata + 1 WHERE id = ?')->execute([(int)$img['id']]);
                    $_SESSION['messaggio'] = ['ok', 'Copertina scelta: foto di '
                        . ($img['autore'] ?: 'autore ignoto') . '.'];
                }
            }

        } elseif ($che === 'togli') {
            $f = cartellaCopertine() . '/' . $a['slug'] . '.jpg';
            if (is_file($f)) { @unlink($f); }
            $pdo->prepare('UPDATE ' . t('articles') . '
                 SET immagine_url = NULL, immagine_autore = NULL, immagine_licenza = NULL,
                     immagine_licenza_url = NULL, immagine_fonte_url = NULL,
                     immagine_origine = NULL, immagine_cercata_il = NULL
               WHERE id = ?')->execute([$id]);
            $_SESSION['messaggio'] = ['ok', 'Copertina tolta. Al prossimo giro ne cerca una.'];
        }

        cacheSvuota();
        vaiA('admin/copertina/' . $id);
    }

    // Il catalogo da cui scegliere. Il soggetto giusto viene per primo,
    // poi il resto: se l'articolo parla di Chino, le sue foto stanno in
    // cima senza però nascondere le altre.
    $tag = json_decode((string)$a['tag'], true) ?: [];
    $sog = soggettoArticolo((string)$a['titolo_it'], $tag, dischiPerTitolo($pdo));
    $mira = $sog['tipo'] === 'foto' ? $sog['chiave'] : 'band';

    $cerca = trim((string)($_GET['q'] ?? ''));
    $dove = ['scartata = 0'];
    $par  = [];
    if ($cerca !== '') {
        $dove[] = '(autore LIKE ? OR riferimento LIKE ?)';
        $par[] = '%' . $cerca . '%';
        $par[] = '%' . $cerca . '%';
    }
    $q = $pdo->prepare('SELECT * FROM ' . t('immagini') . '
                         WHERE ' . implode(' AND ', $dove) . '
                         ORDER BY soggetto = ? DESC, usata ASC, id ASC
                         LIMIT 60');
    // I parametri vanno nell'ordine in cui i punti interrogativi compaiono
    // nella query: prima quelli del WHERE, poi quello dell'ORDER BY.
    $q->execute(array_merge($par, [$mira]));
    $foto = $q->fetchAll();

    echo render('admin-copertina', [
        'a' => $a, 'foto' => $foto, 'mira' => $mira,
        'cerca' => $cerca, 'messaggio' => $msg,
    ], ['titolo' => 'Copertina — pannello']);
    exit;
}

// ------------------------------------------------- nuovo articolo

if ($azione === 'nuovo') {
    $msg = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfValido($_POST['csrf'] ?? null)) {
            $msg = ['ko', 'Sessione scaduta, riprova.'];
        } elseif (trim((string)($_POST['titolo_it'] ?? '')) === '') {
            $msg = ['ko', 'Il titolo serve: da lì nasce anche l\'indirizzo dell\'articolo.'];
        } else {
            $titolo = mb_substr(trim((string)$_POST['titolo_it']), 0, 300);
            $come   = (string)($_POST['come'] ?? 'bozza');
            $stato  = $come === 'bozza' ? 'draft' : 'pubblicato';

            $tag = array_values(array_filter(array_map(
                fn($t) => trim(mb_strtolower($t)),
                explode(',', (string)($_POST['tag'] ?? ''))
            )));
            $quando = trim((string)($_POST['pubblicato_il'] ?? ''));
            $quando = $quando !== '' ? str_replace('T', ' ', $quando) . ':00' : date('Y-m-d H:i:s');

            $catOk = valoriEnum($pdo, t('articles'), 'categoria');
            $attOk = valoriEnum($pdo, t('articles'), 'attendibilita');
            $cat = (string)($_POST['categoria'] ?? '');
            $att = (string)($_POST['attendibilita'] ?? '');

            // Scritto a mano: modello e uso_token restano vuoti, ed è
            // giusto che si veda — sono la traccia di cosa ha generato
            // cosa. La rilevanza la mettiamo alta: se lo scrivi tu,
            // vuol dire che conta.
            $q = $pdo->prepare('INSERT INTO ' . t('articles') . '
                  (slug, titolo_it, sommario_it, corpo_it, categoria, attendibilita,
                   tag, rilevanza, fonte_nome, fonte_url, stato, pubblicato_il, creato_il)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
            $q->execute([
                slugUnico($titolo), $titolo,
                trim((string)($_POST['sommario_it'] ?? '')),
                trim((string)($_POST['corpo_it'] ?? '')) ?: null,
                in_array($cat, $catOk, true) ? $cat : 'news',
                in_array($att, $attOk, true) ? $att : 'confermato',
                $tag ? json_encode($tag, JSON_UNESCAPED_UNICODE) : null,
                80,
                trim((string)($_POST['fonte_nome'] ?? '')) ?: null,
                trim((string)($_POST['fonte_url'] ?? '')) ?: null,
                $stato, $quando,
            ]);
            $id = (int)$pdo->lastInsertId();

            $coda = '';
            if ($come === 'copertina') {
                require_once __DIR__ . '/lib/copertine.php';
                $art = ['id' => $id, 'slug' => slug($titolo), 'titolo_it' => $titolo,
                        'tag' => json_encode($tag)];
                $q = $pdo->prepare('SELECT slug FROM ' . t('articles') . ' WHERE id = ?');
                $q->execute([$id]);
                $art['slug'] = (string)$q->fetchColumn();
                $r = assegnaCopertina($pdo, $art, dischiPerTitolo($pdo));
                $coda = ' — ' . match ($r['origine']) {
                    'disco'   => 'copertina di ' . $r['nota'],
                    'commons' => 'foto di ' . $r['nota'],
                    default   => 'nessuna foto adatta trovata',
                };
            }

            cacheSvuota();
            $_SESSION['messaggio'] = ['ok', ($stato === 'draft'
                ? 'Bozza creata.' : 'Articolo pubblicato.') . $coda];
            vaiA('admin/modifica/' . $id);
        }
    }

    // Lo scheletro di un articolo che non esiste ancora: la stessa vista
    // della modifica, con i campi vuoti.
    echo render('admin-modifica', [
        'a' => [
            'id' => 0, 'slug' => '', 'titolo_it' => '', 'sommario_it' => '',
            'corpo_it' => '', 'categoria' => 'news', 'attendibilita' => 'confermato',
            'tag' => null, 'fonte_nome' => '', 'fonte_url' => '',
            'pubblicato_il' => date('Y-m-d H:i:s'), 'stato' => 'nuovo',
            'immagine_origine' => null,
        ],
        'categorie' => valoriEnum($pdo, t('articles'), 'categoria'),
        'attendib'  => valoriEnum($pdo, t('articles'), 'attendibilita'),
        'messaggio' => $msg,
    ], ['titolo' => 'Nuovo articolo — pannello']);
    exit;
}

// ------------------------------------------------------------ modifica

/** I valori ammessi da una colonna ENUM, letti dalla colonna stessa. */
function valoriEnum(PDO $pdo, string $tabella, string $colonna): array
{
    preg_match_all("/'([^']*)'/", (string)colonnaTipo($pdo, $tabella, $colonna), $m);
    return $m[1] ?? [];
}

if (preg_match('#^modifica/(\d+)$#', $azione, $m)) {
    $id = (int)$m[1];
    $msg = messaggioDiPassaggio();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrfValido($_POST['csrf'] ?? null)) {
            $msg = ['ko', 'Sessione scaduta, riprova.'];
        } else {
            // I tag si scrivono separati da virgola e si salvano come
            // JSON, che è il formato in cui li mette il modello.
            $tag = array_values(array_filter(array_map(
                fn($t) => trim(mb_strtolower($t)),
                explode(',', (string)($_POST['tag'] ?? ''))
            )));

            $quando = trim((string)($_POST['pubblicato_il'] ?? ''));
            $quando = $quando !== '' ? str_replace('T', ' ', $quando) . ':00' : null;

            $cat = (string)($_POST['categoria'] ?? '');
            $att = (string)($_POST['attendibilita'] ?? '');
            $catOk = valoriEnum($pdo, t('articles'), 'categoria');
            $attOk = valoriEnum($pdo, t('articles'), 'attendibilita');

            $q = $pdo->prepare('UPDATE ' . t('articles') . '
                 SET titolo_it = ?, sommario_it = ?, corpo_it = ?,
                     categoria = ?, attendibilita = ?, tag = ?,
                     fonte_nome = ?, fonte_url = ?, pubblicato_il = ?
               WHERE id = ?');
            $q->execute([
                mb_substr(trim((string)($_POST['titolo_it'] ?? '')), 0, 300),
                trim((string)($_POST['sommario_it'] ?? '')),
                trim((string)($_POST['corpo_it'] ?? '')) ?: null,
                in_array($cat, $catOk, true) ? $cat : 'news',
                in_array($att, $attOk, true) ? $att : 'confermato',
                $tag ? json_encode($tag, JSON_UNESCAPED_UNICODE) : null,
                trim((string)($_POST['fonte_nome'] ?? '')) ?: null,
                trim((string)($_POST['fonte_url'] ?? '')) ?: null,
                $quando,
                $id,
            ]);
            $n = cacheSvuota();
            $msg = ['ok', "Salvato. Cache svuotata ($n pagine)."];
        }
    }

    $q = $pdo->prepare('SELECT * FROM ' . t('articles') . ' WHERE id = ? LIMIT 1');
    $q->execute([$id]);
    $a = $q->fetch();
    if (!$a) { pagina404(); }

    echo render('admin-modifica', [
        'a'          => $a,
        'categorie'  => valoriEnum($pdo, t('articles'), 'categoria'),
        'attendib'   => valoriEnum($pdo, t('articles'), 'attendibilita'),
        'messaggio'  => $msg,
    ], ['titolo' => 'Modifica — pannello']);
    exit;
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

$pubblicate = $pdo->query('SELECT id, slug, titolo_it, pubblicato_il, fonte_nome, in_apertura
                             FROM ' . t('articles') . '
                            WHERE stato = \'pubblicato\'
                            ORDER BY pubblicato_il DESC LIMIT 15')->fetchAll();

$ultimo = $pdo->query('SELECT job, finito_il, esito, item_elaborati, messaggio
                         FROM ' . t('run_log') . '
                        ORDER BY id DESC LIMIT 5')->fetchAll();

echo render('admin-bozze', [
    'bozze' => $bozze, 'pubblicate' => $pubblicate, 'ultimo' => $ultimo,
    'messaggio' => $messaggio ?? messaggioDiPassaggio(), 'totale' => $totale, 'pagina' => $pagina,
    'pagine' => $pagine, 'cerca' => $cerca, 'anno' => $anno, 'cat' => $cat,
    'ordine' => $ordine, 'anni' => $anni, 'categorie' => $categorie,
], ['titolo' => 'Pannello — deftones.it']);
