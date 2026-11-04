<?php
require_once __DIR__ . '/conexao.php';
exigir_login();

$uid = (int)usuario_logado()['id'];

$q = $pdo->prepare("SELECT id, nome, email, perfil, bio, genero_favorito, criado_em FROM usuarios WHERE id = :id");
$q->execute([':id' => $uid]);
$conta = $q->fetch();
if (!$conta) {
    unset($_SESSION['usuario']);
    redirecionar('login.php');
}

$generos = $pdo->query("SELECT DISTINCT genero FROM livros
                         WHERE genero IS NOT NULL AND genero <> '' ORDER BY genero")->fetchAll(PDO::FETCH_COLUMN);

$erros_dados = [];
$erros_senha = [];
$f = [
    'nome'   => (string)$conta['nome'],
    'email'  => (string)$conta['email'],
    'bio'    => (string)($conta['bio'] ?? ''),
    'genero' => (string)($conta['genero_favorito'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = campo('acao');

    if ($acao === 'dados') {
        // ---------- Atualizar dados do perfil ----------
        $f = [
            'nome'   => campo('nome'),
            'email'  => mb_strtolower(campo('email')),
            'bio'    => campo('bio'),
            'genero' => campo('genero'),
        ];
        if (mb_strlen($f['nome']) < 2 || mb_strlen($f['nome']) > 100)                         $erros_dados[] = 'Informe seu nome (2 a 100 caracteres).';
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($f['email']) > 150) $erros_dados[] = 'Informe um e-mail válido.';
        if (mb_strlen($f['bio']) > 200)                                                       $erros_dados[] = 'O texto "sobre você" pode ter até 200 caracteres.';
        if ($f['genero'] !== '' && !in_array($f['genero'], $generos, true))                   $erros_dados[] = 'Escolha um gênero da lista.';

        if (!$erros_dados) {
            $c = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email AND id <> :id");
            $c->execute([':email' => $f['email'], ':id' => $uid]);
            if ((int)$c->fetchColumn() > 0) $erros_dados[] = 'Esse e-mail já está em uso por outra conta.';
        }

        if (!$erros_dados) {
            try {
                $c = $pdo->prepare("UPDATE usuarios
                                    SET nome = :nome, email = :email, bio = :bio, genero_favorito = :genero
                                    WHERE id = :id");
                $c->execute([
                    ':nome'   => $f['nome'],
                    ':email'  => $f['email'],
                    ':bio'    => $f['bio'] !== '' ? $f['bio'] : null,
                    ':genero' => $f['genero'] !== '' ? $f['genero'] : null,
                    ':id'     => $uid,
                ]);
                $_SESSION['usuario']['nome'] = $f['nome'];
                flash('Perfil atualizado.');
                redirecionar('perfil.php');
            } catch (PDOException $ex) {
                if ($ex->getCode() === '23000') {
                    $erros_dados[] = 'Esse e-mail já está em uso por outra conta.';
                } else {
                    error_log('Lunaris Library: erro ao atualizar perfil - ' . $ex->getMessage());
                    $erros_dados[] = 'Não foi possível salvar o perfil. Tente novamente.';
                }
            }
        }
    } elseif ($acao === 'senha') {
        // ---------- Trocar senha ----------
        $atual = campo('senha_atual', false);
        $nova  = campo('senha_nova', false);
        $conf  = campo('senha_conf', false);

        $c = $pdo->prepare("SELECT senha_hash FROM usuarios WHERE id = :id");
        $c->execute([':id' => $uid]);
        $hash = (string)$c->fetchColumn();

        if (!password_verify($atual, $hash)) $erros_senha[] = 'A senha atual está incorreta.';
        if (mb_strlen($nova) < 8)            $erros_senha[] = 'A nova senha deve ter pelo menos 8 caracteres.';
        if (strlen($nova) > 72)              $erros_senha[] = 'A nova senha pode ter no máximo 72 caracteres.';
        if ($nova !== $conf)                 $erros_senha[] = 'A confirmação não confere com a nova senha.';

        if (!$erros_senha) {
            $c = $pdo->prepare("UPDATE usuarios SET senha_hash = :hash WHERE id = :id");
            $c->execute([':hash' => password_hash($nova, PASSWORD_DEFAULT), ':id' => $uid]);
            session_regenerate_id(true);
            flash('Senha alterada.');
            redirecionar('perfil.php');
        }
    }
}

// ---------- Histórico ----------
$c = $pdo->prepare("SELECT a.nota, a.atualizado_em, l.id AS livro_id, l.titulo
                    FROM avaliacoes a JOIN livros l ON l.id = a.livro_id
                    WHERE a.usuario_id = :u ORDER BY a.atualizado_em DESC LIMIT 6");
$c->execute([':u' => $uid]);
$minhas_notas = $c->fetchAll();

$c = $pdo->prepare("SELECT id, nome, turma FROM alunos WHERE usuario_id = :u LIMIT 1");
$c->execute([':u' => $uid]);
$aluno = $c->fetch();

$meus_emprestimos = [];
if ($aluno) {
    $c = $pdo->prepare("SELECT l.id AS livro_id, l.titulo, e.data_emprestimo, e.data_devolucao
                        FROM emprestimos e JOIN livros l ON l.id = e.livro_id
                        WHERE e.aluno_id = :a
                        ORDER BY e.data_devolucao IS NOT NULL, e.data_emprestimo DESC, e.id DESC LIMIT 6");
    $c->execute([':a' => (int)$aluno['id']]);
    $meus_emprestimos = $c->fetchAll();
}

$sugestoes = recomendar_livros($pdo, $uid, 4);
$hoje      = date('Y-m-d');

$titulo_pagina = 'Meu perfil';
require_once __DIR__ . '/topo.php';
?>

<div class="perfil-topo">
  <span class="avatar avatar-grande"><?= e(mb_strtoupper(mb_substr($conta['nome'], 0, 1))) ?></span>
  <div>
    <h1><?= e($conta['nome']) ?></h1>
    <p class="sub">
      <?= $conta['perfil'] === 'admin' ? 'Bibliotecário(a)' : 'Leitor(a)' ?>
      <?= $conta['criado_em'] ? ' desde ' . e(data_br($conta['criado_em'])) : '' ?>
    </p>
    <?php if (!empty($conta['bio'])): ?><p class="bio"><?= e($conta['bio']) ?></p><?php endif; ?>
  </div>
</div>

<section class="duas-colunas">
  <div class="card">
    <h2>Seus dados</h2>
    <?php if ($erros_dados): ?>
      <div class="alerta alerta-erro"><ul><?php foreach ($erros_dados as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" class="form">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="dados">
      <label>Nome
        <input name="nome" value="<?= e($f['nome']) ?>" required maxlength="100" autocomplete="name">
      </label>
      <label>E-mail
        <input type="email" name="email" value="<?= e($f['email']) ?>" required maxlength="150" autocomplete="email">
      </label>
      <label>Sobre você <small class="dica">opcional</small>
        <input name="bio" value="<?= e($f['bio']) ?>" maxlength="200" placeholder="Uma frase sobre o que você gosta de ler">
      </label>
      <label>Gênero favorito <small class="dica">ajuda nas sugestões</small>
        <select name="genero">
          <option value="">Sem preferência</option>
          <?php foreach ($generos as $g): ?>
            <option value="<?= e($g) ?>" <?= $g === $f['genero'] ? 'selected' : '' ?>><?= e($g) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <div><button class="btn btn-primario">Salvar alterações</button></div>
    </form>
  </div>

  <div class="card">
    <h2>Trocar senha</h2>
    <?php if ($erros_senha): ?>
      <div class="alerta alerta-erro"><ul><?php foreach ($erros_senha as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" class="form">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="senha">
      <label>Senha atual
        <input type="password" name="senha_atual" required autocomplete="current-password">
      </label>
      <label>Nova senha <small class="dica">mínimo de 8 caracteres</small>
        <input type="password" name="senha_nova" required minlength="8" maxlength="72" autocomplete="new-password">
      </label>
      <label>Confirmar nova senha
        <input type="password" name="senha_conf" required minlength="8" maxlength="72" autocomplete="new-password">
      </label>
      <div><button class="btn btn-fantasma">Alterar senha</button></div>
    </form>
  </div>
</section>

<?php if ($sugestoes): ?>
<section class="secao-sugestoes">
  <h2 class="titulo-secao">Sugestões para você</h2>
  <div class="grade-livros">
    <?php foreach ($sugestoes as $s): ?>
      <?= card_livro($s, (string)$s['motivo']) ?>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="duas-colunas secao-historico">
  <div>
    <h2 class="titulo-secao">Minhas avaliações</h2>
    <?php if (!$minhas_notas): ?>
      <div class="vazio">Você ainda não avaliou nenhum livro.</div>
    <?php else: ?>
      <ul class="lista-simples">
        <?php foreach ($minhas_notas as $n): ?>
          <li>
            <a href="livro.php?id=<?= (int)$n['livro_id'] ?>"><?= e($n['titulo']) ?></a>
            <span><?= estrelas($n['nota']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div>
    <h2 class="titulo-secao">Meus empréstimos</h2>
    <?php if (!$aluno): ?>
      <div class="vazio">Sua conta ainda não está ligada a um cadastro de aluno. Peça à bibliotecária para vincular, e seus empréstimos passam a aparecer aqui e a melhorar as sugestões.</div>
    <?php elseif (!$meus_emprestimos): ?>
      <div class="vazio">Nenhum empréstimo até agora.</div>
    <?php else: ?>
      <ul class="lista-simples">
        <?php foreach ($meus_emprestimos as $emp):
          $aberto   = $emp['data_devolucao'] === null;
          $prazo    = date('Y-m-d', strtotime($emp['data_emprestimo'] . ' +' . (int)PRAZO_DIAS . ' days'));
          $atrasado = $aberto && $prazo < $hoje;
        ?>
          <li>
            <a href="livro.php?id=<?= (int)$emp['livro_id'] ?>"><?= e($emp['titulo']) ?></a>
            <?php if ($aberto): ?>
              <span class="badge <?= $atrasado ? 'badge-erro' : 'badge-aviso' ?>">
                <?= $atrasado ? 'Atrasado' : 'Devolver até ' . e(data_br($prazo)) ?>
              </span>
            <?php else: ?>
              <span class="badge badge-ok">Devolvido</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/rodape.php'; ?>
