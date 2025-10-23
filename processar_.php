<?php
// Conexão com o banco de dados

// Processamento dos dados
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $conn = conectar_db();

    if (isset($_POST['acao']) && $_POST['acao'] == 'Adicionar Registro') {
        // Adicionar um novo registro
        $numcra = $_POST['NUMCRA'];
        $datacc = $_POST['DATACC'];
        $horacc = $_POST['HORACC'];
        $diracc = $_POST['DIRACC'];

        $sql = "INSERT INTO vetorh.R070ACC (NUMCRA, DATACC, HORACC, DIRACC) VALUES (:numcra, TO_DATE(:datacc, 'YYYY-MM-DD'), :horacc, :diracc)";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':numcra', $numcra);
        oci_bind_by_name($stmt, ':datacc', $datacc);
        oci_bind_by_name($stmt, ':horacc', $horacc);
        oci_bind_by_name($stmt, ':diracc', $diracc);

        if (oci_execute($stmt)) {
            echo "Registro adicionado com sucesso!";
        } else {
            echo "Erro ao adicionar registro: " . oci_error($stmt)['message'];
        }
    } elseif (isset($_POST['acao']) && $_POST['acao'] == 'Corrigir') {
        // Corrigir um registro existente (implementar lógica de correção)
        $id = $_POST['ID'];
        // Aqui você pode redirecionar para um formulário de correção ou implementar lógica diretamente
    } elseif (isset($_POST['acao']) && $_POST['acao'] == 'Excluir') {
        // Excluir um registro
        $id = $_POST['ID'];

        $sql = "DELETE FROM vetorh.R070ACC WHERE NUMCRA = :id";
        $stmt = oci_parse($conn, $sql);
        oci_bind_by_name($stmt, ':id', $id);

        if (oci_execute($stmt)) {
            echo "Registro excluído com sucesso!";
        } else {
            echo "Erro ao excluir registro: " . oci_error($stmt)['message'];
        }
    }

    oci_close($conn);
}

// Consulta aos dados
$conn = conectar_db();
$numcad = 383; // ID do colaborador
$datacc = '04/11/2024'; // Data a ser consultada

$sql = "SELECT NUMCRA, DATACC, HORACC, DIRACC FROM vetorh.R070ACC WHERE NUMCAD = :numcad AND DATACC = TO_DATE(:datacc, 'DD/MM/YYYY')";
$stmt = oci_parse($conn, $sql);
oci_bind_by_name($stmt, ':numcad', $numcad);
oci_bind_by_name($stmt, ':datacc', $datacc);
oci_execute($stmt);

// Exibir dados
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Ponto</title>
    <link rel="stylesheet" href="style.css"> <!-- Link para o arquivo CSS -->
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input[type="submit"] {
            background-color: #28a745;
            color: white;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Dados do Colaborador</h1>
        <table>
            <tr><th>Número</th><th>Data</th><th>Horário (minutos)</th><th>Direção</th><th>Ações</th></tr>
            <?php
            while ($row = oci_fetch_array($stmt, OCI_ASSOC + OCI_RETURN_NULLS)) {
                echo "<tr>";
                echo "<td>{$row['NUMCRA']}</td>";
                echo "<td>{$row['DATACC']}</td>";
                echo "<td>{$row['HORACC']}</td>";
                echo "<td>{$row['DIRACC']}</td>";
                echo "<td>";
                echo "<form method='post' action='gerenciar_ponto.php' style='display:inline;'>";
                echo "<input type='hidden' name='ID' value='{$row['NUMCRA']}'>";
                echo "<input type='submit' name='acao' value='Corrigir'>";
                echo "</form>";
                echo "<form method='post' action='gerenciar_ponto.php' style='display:inline;'>";
                echo "<input type='hidden' name='ID' value='{$row['NUMCRA']}'>";
                echo "<input type='submit' name='acao' value='Excluir'>";
                echo "</form>";
                echo "</td>";
                echo "</tr>";
            }
            ?>
        </table>

        <h2>Adicionar Novo Registro</h2>
        <form method='post' action='gerenciar_ponto.php'>
            <input type='number' name='NUMCRA' placeholder='Número do Colaborador' required>
            <input type='date' name='DATACC' required>
            <input type='number' name='HORACC' placeholder='Horário (minutos)' required>
            <select name='DIRACC' required>
                <option value='E'>Entrada</option>
                <option value='S'>Saída</option>
            </select>
            <input type='submit' name='acao' value='Adicionar Registro'>
        </form>
    </div>
</body>
</html>
<?php
oci_close($conn);
?>
