<?php
require_once __DIR__ . '/conexao.php';

if (usuario_logado()) redirecionar('index.php');

$erros = [];
$nome  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $nome      = campo('nome');
    $email     = mb_strtolower(campo('email'));
    $senha     = campo('senha', false);
    $confirmar = campo('confirmar', false);

    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100)                        $erros[] = 'Informe seu nome (2 a 100 caracteres).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) $erros[] = 'Informe um e-mail válido.';
    if (mb_strlen($senha) < 8)                                                  $erros[] = 'A senha deve ter pelo menos 8 caracteres.';
    if (strlen($senha) > 72)                                                    $erros[] = 'A senha pode ter no máximo 72 caracteres.';
    if ($senha !== $confirmar)                                                  $erros[] = 'As senhas não conferem.';

    if (!$erros) {
        $consulta = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
        $consulta->execute([':email' => $email]);
        if ((int)$consulta->fetchColumn() > 0) $erros[] = 'Já existe uma conta com esse e-mail.';
    }

    if (!$erros) {
        try {
            // A primeira conta criada vira bibliotecário (admin); as demais, leitores.
            $total  = (int)$pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
            $perfil = $total === 0 ? 'admin' : 'leitor';

            $consulta = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, perfil)
                                       VALUES (:nome, :email, :hash, :perfil)");
            $consulta->execute([
                ':nome'   => $nome,
                ':email'  => $email,
                ':hash'   => password_hash($senha, PASSWORD_DEFAULT),
                ':perfil' => $perfil,
            ]);

            session_regenerate_id(true);
            $_SESSION['usuario'] = [
                'id'     => (int)$pdo->lastInsertId(),
                'nome'   => $nome,
                'perfil' => $perfil,
            ];
            flash('Conta criada! Bem-vindo(a) ao Lunaris Library.');
            redirecionar('index.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {
                $erros[] = 'Já existe uma conta com esse e-mail.';
            } else {
                error_log('Lunaris Library: erro ao criar conta - ' . $ex->getMessage());
                $erros[] = 'Não foi possível criar a conta. Tente novamente.';
            }
        }
    }
}

$titulo_pagina = 'Criar conta';
$pagina_auth   = true;
require_once __DIR__ . '/topo.php';
?>

<div class="auth-card">
  <div class="marca-grande">Lunaris Library</div>
  <p class="auth-sub">Crie sua conta de leitor</p>

  <?php if ($erros): ?>
    <div class="alerta alerta-erro"><ul><?php foreach ($erros as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" class="form">
    <?= csrf_campo() ?>
    <label>Nome
      <input name="nome" value="<?= e($nome) ?>" required maxlength="100" autocomplete="name">
    </label>
    <label>E-mail
      <input type="email" name="email" value="<?= e($email) ?>" required maxlength="150" autocomplete="email">
    </label>
    <label>Senha <small class="dica">mínimo de 8 caracteres</small>
      <input type="password" name="senha" required minlength="8" maxlength="72" autocomplete="new-password">
    </label>
    <label>Confirmar senha
      <input type="password" name="confirmar" required minlength="8" maxlength="72" autocomplete="new-password">
    </label>
    <button class="btn btn-primario btn-bloco">Criar conta</button>
  </form>

  <p class="auth-rodape">Já tem conta? <a href="login.php">Entrar</a></p>
</div>

<?php require_once __DIR__ . '/rodape.php'; ?>
