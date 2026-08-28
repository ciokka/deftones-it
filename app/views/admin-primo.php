<div class="accesso">
  <h1>Primo accesso</h1>
  <p style="color:var(--testo-tenue);font-size:.9375rem;margin-bottom:1.5rem">
    Non esiste ancora nessun utente. Creane uno: questa pagina si disattiva
    da sola appena l'hai fatto.
  </p>
  <?php if ($errore): ?><div class="avvisoKo"><?= e($errore) ?></div><?php endif ?>
  <form method="post">
    <label class="campo"><span>Nome utente</span>
      <input type="text" name="utente" required autocomplete="username" autofocus></label>
    <label class="campo"><span>Password (almeno 10 caratteri)</span>
      <input type="password" name="password" required autocomplete="new-password"></label>
    <button class="bottone" type="submit">Crea l'utente</button>
  </form>
</div>
