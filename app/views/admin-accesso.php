<div class="accesso">
  <h1>Accesso</h1>
  <?php if ($errore): ?><div class="avvisoKo"><?= e($errore) ?></div><?php endif ?>
  <form method="post">
    <label class="campo"><span>Nome utente</span>
      <input type="text" name="utente" required autocomplete="username" autofocus></label>
    <label class="campo"><span>Password</span>
      <input type="password" name="password" required autocomplete="current-password"></label>
    <button class="bottone" type="submit">Entra</button>
  </form>
</div>
