<?php
/* =========================================================
   Trata · conexão, sessão e funções auxiliares
   ========================================================= */

date_default_timezone_set('America/Sao_Paulo');

// ---------- Sessão ----------
if (session_status() === PHP_SESSION_NONE) {
    session_name('trata_sessao');
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// Qualquer erro inesperado vira uma mensagem amigável (o detalhe vai para o log do PHP)
set_exception_handler(function (Throwable $t) {
    error_log('Trata: ' . $t);
    if (!headers_sent()) http_response_code(500);
    echo 'Ocorreu um erro inesperado. Confirme que o arquivo banco.sql foi executado no phpMyAdmin e tente novamente.';
});

const PRAZO_DIAS = 14; // prazo padrão de devolução

// ---------- Banco de dados ----------
$host    = 'localhost';
$db      = 'biblioteca';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    error_log('Trata: falha na conexão - ' . $e->getMessage());
    http_response_code(500);
    die('Não foi possível conectar ao banco de dados. Verifique se o MariaDB está ativo no XAMPP e se o banco "biblioteca" foi criado (veja banco.sql).');
}

// ---------- Utilidades ----------
// Escapa texto para HTML (proteção contra XSS)
function e($string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

// Lê um campo de $_POST como texto (ignora arrays maliciosos)
function campo(string $nome, bool $aparar = true): string {
    $v = $_POST[$nome] ?? '';
    if (!is_string($v)) return '';
    return $aparar ? trim($v) : $v;
}

// Lê um parâmetro de $_GET como texto (ignora arrays, que derrubariam o trim)
function parametro(string $nome): string {
    $v = $_GET[$nome] ?? '';
    return is_string($v) ? trim($v) : '';
}

function redirecionar(string $url): void {
    header('Location: ' . $url);
    exit;
}

function data_br($data): string {
    return $data ? date('d/m/Y', strtotime((string)$data)) : '';
}

// Mensagens que aparecem uma única vez após um redirecionamento
function flash(string $msg, string $tipo = 'ok'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'tipo' => $tipo];
}
function pegar_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------- Autenticação ----------
function usuario_logado(): ?array {
    return $_SESSION['usuario'] ?? null;
}
function eh_admin(): bool {
    return (usuario_logado()['perfil'] ?? '') === 'admin';
}
function exigir_login(): void {
    if (!usuario_logado()) redirecionar('login.php');
}
function exigir_admin(): void {
    exigir_login();
    if (!eh_admin()) {
        flash('Você não tem permissão para acessar essa página.', 'erro');
        redirecionar('index.php');
    }
}

// Mantém nome e perfil da sessão em dia com o banco (e derruba sessões de contas removidas)
if (!empty($_SESSION['usuario']['id'])) {
    $q = $pdo->prepare("SELECT id, nome, perfil FROM usuarios WHERE id = :id");
    $q->execute([':id' => (int)$_SESSION['usuario']['id']]);
    $conta_sessao = $q->fetch();
    if ($conta_sessao) {
        $_SESSION['usuario'] = [
            'id'     => (int)$conta_sessao['id'],
            'nome'   => $conta_sessao['nome'],
            'perfil' => $conta_sessao['perfil'],
        ];
    } else {
        unset($_SESSION['usuario']);
    }
    unset($q, $conta_sessao);
}

// ---------- CSRF (proteção dos formulários POST) ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_campo(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_validar(): void {
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals((string)($_SESSION['csrf'] ?? ''), $t)) {
        http_response_code(419);
        die('Sessão expirada. Volte à página anterior, recarregue e tente novamente.');
    }
}

// ---------- Avaliação em estrelas ----------
function estrelas($media): string {
    $n = max(0.0, min(5.0, (float)$media));
    return '<span class="estrelas" style="--n:' . number_format($n, 2, '.', '') . '" role="img" aria-label="Nota '
         . number_format($n, 1, ',', '.') . ' de 5">★★★★★</span>';
}

// Cor da capa (0 a 5) escolhida a partir do título
function capa_cor(string $titulo): int {
    return abs(crc32($titulo)) % 6;
}

// ---------- Validação de livro (usada em novo.php e editar.php) ----------
function validar_livro(): array {
    $d = [
        'titulo'     => campo('titulo'),
        'autor'      => campo('autor'),
        'ano'        => campo('ano'),
        'genero'     => campo('genero'),
        'quantidade' => campo('quantidade'),
    ];
    $erros = [];
    if ($d['titulo'] === '' || mb_strlen($d['titulo']) > 150) $erros[] = 'Informe o título (até 150 caracteres).';
    if ($d['autor'] === '' || mb_strlen($d['autor']) > 100)   $erros[] = 'Informe o autor (até 100 caracteres).';
    if ($d['ano'] !== '' && (!ctype_digit($d['ano']) || (int)$d['ano'] < 1000 || (int)$d['ano'] > (int)date('Y') + 1)) {
        $erros[] = 'Ano inválido.';
    }
    if (mb_strlen($d['genero']) > 50) $erros[] = 'O gênero pode ter até 50 caracteres.';
    if (!ctype_digit($d['quantidade']) || (int)$d['quantidade'] < 1 || (int)$d['quantidade'] > 9999) {
        $erros[] = 'A quantidade deve ser um número maior que zero.';
    }
    return [$d, $erros];
}

// Peças que mudam de acordo com o visual escolhido e o motor de recomendações
require_once __DIR__ . '/componentes.php';
require_once __DIR__ . '/recomendacoes.php';
