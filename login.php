<?php
require_once __DIR__ . '/conexao.php';

if (usuario_logado()) redirecionar('index.php');

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $email = mb_strtolower(campo('email'));
    $senha = campo('senha', false);
    $agora = time();

    if ((int)($_SESSION['login_bloqueio'] ?? 0) > $agora) {
        $erro = 'Muitas tentativas. Aguarde ' . ((int)$_SESSION['login_bloqueio'] - $agora) . ' segundos e tente de novo.';
    } else {
        $consulta = $pdo->prepare("SELECT id, nome, senha_hash, perfil FROM usuarios WHERE email = :email");
        $consulta->execute([':email' => $email]);
        $usr = $consulta->fetch();

        if ($usr && password_verify($senha, $usr['senha_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['login_falhas'], $_SESSION['login_bloqueio']);
            $_SESSION['usuario'] = [
                'id'     => (int)$usr['id'],
                'nome'   => $usr['nome'],
                'perfil' => $usr['perfil'],
            ];
            redirecionar('index.php');
        }

        // Após 5 erros seguidos, espera 60 segundos antes de aceitar nova tentativa
        $_SESSION['login_falhas'] = (int)($_SESSION['login_falhas'] ?? 0) + 1;
        if ($_SESSION['login_falhas'] >= 5) {
            $_SESSION['login_falhas']   = 0;
            $_SESSION['login_bloqueio'] = $agora + 60;
        }
        $erro = 'E-mail ou senha incorretos.';
    }
}

$titulo_pagina = 'Entrar';
$pagina_auth   = true;
require_once __DIR__ . '/topo.php';
?>

<div class="auth-card">
  <div class="marca-grande">Lunaris Library</div>
  <p class="auth-sub">Entre para explorar o acervo</p>

  <?php if ($erro): ?><div class="alerta alerta-erro"><?= e($erro) ?></div><?php endif; ?>

  <form method="post" class="form">
    <?= csrf_campo() ?>
    <label>E-mail
      <input type="email" name="email" value="<?= e($email) ?>" required autofocus autocomplete="email">
    </label>
    <label>Senha
      <input type="password" name="senha" required autocomplete="current-password">
    </label>
    <button class="btn btn-primario btn-bloco">Entrar</button>
  </form>

  <p class="auth-rodape">Ainda não tem conta? <a href="cadastro.php">Criar conta</a></p>
</div>

<?php require_once __DIR__ . '/rodape.php'; ?>
