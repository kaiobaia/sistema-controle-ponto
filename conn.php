<?php
function conectar_db()
{
    $conn = oci_connect('vetorh', 'rec07gf7', '192.168.50.11/senior'); // Ajuste as credenciais conforme necessário
    if (!$conn) {
        $e = oci_error();
        die("Erro de conexão: " . $e['message']);
    }
    return $conn;
}
