<?php


// Verificar se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Conectar ao banco de dados

    echo "Formulário recebido!";

    // Obter os dados do formulário
    $idprocesso = isset($_POST['idprocesso']) ? $_POST['idprocesso'] : null;
    $nomepresidentecee = isset($_POST['nomepresidentecee']) ? $_POST['nomepresidentecee'] : '';
    $nomesecretariocee = isset($_POST['nomesecretariocee']) ? $_POST['nomesecretariocee'] : '';

    // Validar e sanitizar os inputs
    $idprocesso = filter_var($idprocesso, FILTER_VALIDATE_INT);
    $nomepresidentecee = filter_var($nomepresidentecee, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $nomesecretariocee = filter_var($nomesecretariocee, FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    try {
        // Função para verificar e validar o nome no banco de dados
        function validarNome($nome, $myBD) {
            $sql = "SELECT nome FROM usuario WHERE LOWER(nome) = LOWER(:nome)";
            $stmt = $myBD->prepare($sql);
            $stmt->bindParam(':nome', $nome, PDO::PARAM_STR);
            $stmt->execute();
            $resultados = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Se houver mais de um nome similar, retornar erro
            if (count($resultados) > 1) {
                throw new Exception("Há mais de um usuário com o nome '{$nome}'. Certifique-se de usar o nome completo e único.");
            }

            // Se o nome exato for encontrado, retorna o nome do banco
            if (count($resultados) === 1) {
                return $resultados[0];
            }

            // Caso não encontre nenhum nome, retornar o original
            return $nome;
        }

        // Validar os nomes do presidente e secretário
        $nomepresidentecee = validarNome($nomepresidentecee, $myBD);
        $nomesecretariocee = validarNome($nomesecretariocee, $myBD);

        // Preparar a query de atualização no banco de dados
        $sql = "UPDATE processo SET 
                    nomepresidentecee = :nomepresidentecee, 
                    nomesecretariocee = :nomesecretariocee 
                WHERE idprocesso = :idprocesso";

        $stmt = $myBD->prepare($sql);
        $stmt->bindParam(':nomepresidentecee', $nomepresidentecee, PDO::PARAM_STR);
        $stmt->bindParam(':nomesecretariocee', $nomesecretariocee, PDO::PARAM_STR);
        $stmt->bindParam(':idprocesso', $idprocesso, PDO::PARAM_INT);

        // Executar a consulta
        $stmt->execute();

        // Redirecionar ou mostrar uma mensagem de sucesso
        enviaMsg("sucesso","Processo atualizado com sucesso");
					
					echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
					exit();

    } catch (Exception $e) {
        echo "Erro: " . $e->getMessage();
    } catch (PDOException $e) {
        echo "Erro ao salvar os dados: " . $e->getMessage();
    }
}
?>
