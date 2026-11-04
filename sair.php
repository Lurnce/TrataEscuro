<?php
require_once __DIR__ . '/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar('index.php');
csrf_validar();

// Limpa os dados da sessão e troca o identificador, sem precisar reabrir a sessão
$_SESSION = [];
session_regenerate_id(true);
flash('Você saiu da sua conta.');
redirecionar('login.php');
