<?php
// Visual "Escuro": menu lateral, vitrine em lista de livros
$u             = usuario_logado();
$arquivo       = basename($_SERVER['SCRIPT_NAME']);
$titulo_pagina = $titulo_pagina ?? 'Acervo';
$pagina_auth   = $pagina_auth ?? false;

// [rótulo, link, páginas que o deixam ativo, ícone (traçados SVG 24x24)]
$menu = [['Acervo', 'index.php', ['index.php', 'livro.php', 'editar.php'],
          '<path d="M2 4h6a4 4 0 0 1 4 4v12a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v12a3 3 0 0 1 3-3h7z"/>']];
if (eh_admin()) {
    $menu[] = ['Novo livro',  'novo.php',        ['novo.php'],        '<path d="M12 5v14M5 12h14"/>'];
    $menu[] = ['Empréstimos', 'emprestimos.php', ['emprestimos.php'], '<path d="M4 8h14l-3-3M20 16H6l3 3"/>'];
    $menu[] = ['Alunos',      'alunos.php',      ['alunos.php'],      '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>'];
}
$menu[] = ['Meu perfil', 'perfil.php', ['perfil.php'], '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'];
$flash = pegar_flash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="dark">
  <title><?= e($titulo_pagina) ?> · Lunaris Library</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="estilo.css">
</head>
<body class="<?= $pagina_auth ? 'auth' : '' ?>">
<?php if (!$pagina_auth): ?>
<div class="app">
  <aside class="lateral">
    <a class="marca" href="index.php" aria-label="Lunaris Library, página inicial">
      <svg viewBox="0 0 28 28" width="28" height="28" aria-hidden="true">
        <rect x="3" y="6" width="6" height="19" rx="1.5" fill="currentColor"/>
        <rect x="11" y="3" width="6" height="22" rx="1.5" fill="#4FD1BC"/>
        <rect x="19.5" y="7" width="6" height="18" rx="1.5" transform="rotate(10 22.5 16)" fill="currentColor" opacity=".55"/>
      </svg>
      <span>Lunaris Library</span>
    </a>
    <nav class="menu" aria-label="Principal">
      <?php foreach ($menu as [$rotulo, $href, $arquivos, $icone]): ?>
        <a href="<?= e($href) ?>" class="<?= in_array($arquivo, $arquivos, true) ? 'ativo' : '' ?>">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icone ?></svg>
          <span><?= e($rotulo) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php if ($u): ?>
    <div class="usuario">
      <span class="avatar"><?= e(mb_strtoupper(mb_substr($u['nome'], 0, 1))) ?></span>
      <span class="usuario-info">
        <strong><?= e($u['nome']) ?></strong>
        <small><?= $u['perfil'] === 'admin' ? 'Bibliotecário(a)' : 'Leitor(a)' ?></small>
      </span>
      <form method="post" action="sair.php">
        <?= csrf_campo() ?>
        <button class="btn btn-sm btn-fantasma">Sair</button>
      </form>
    </div>
    <?php endif; ?>
  </aside>
  <div class="conteudo">
<main class="container">
<?php else: ?>
<main class="auth-box">
<?php endif; ?>

<?php if ($flash): ?>
  <div class="alerta alerta-<?= $flash['tipo'] === 'erro' ? 'erro' : 'ok' ?>" role="status"><?= e($flash['msg']) ?></div>
<?php endif; ?>
