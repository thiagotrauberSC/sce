<?php

class ProcessoDAO {

    private $conn;

    public function __construct() {
        $registry = Registry::getInstance();
        $this->conn = $registry->get('Connection');
    }

    /* Função para retornar todos os processos permitindo paginação */
    public function index($paginacao_inicio, $busca=NULL, $order=NULL, $ascdesc=NULL,$arrayStatus=NULL,$arrayOrigem=NULL) {
        $result=false;
        $orderby="ultimaalteracao DESC";
        if($order!=NULL && $ascdesc!=NULL){
            if($order=="numero"){
                $orderby="p.numero ".$ascdesc;
            }
            if($order=="etapa"){
                $orderby="e.ordem ".$ascdesc;
            }
            if($order=="instituicao"){
                $orderby="u.nome_instituicao ".$ascdesc;
            }
            if($order=="municipio"){
                $orderby="m.nome ".$ascdesc;
            }
            if($order=="dtprazo"){
                $orderby="p.dtprazo ".$ascdesc;
            }
        }
        //variavel que ira adicionar restrições de exibição de processo, ou não..
        $filtro_processos='';
        //se o usuário tiver restrição de processos:
        if(isset($_SESSION['USUARIO']['processos'])){
            //inicializa o filtro
            $filtro_processos=' AND ( ';
            //para cada processo encontrado, adicione ele como SQL
            for($i=0;$i<sizeof($_SESSION['USUARIO']['processos']);$i++){
                $filtro_processos.=' p.idprocesso='.$_SESSION['USUARIO']['processos'][$i].' OR';
            }
            //ao final do loop remove os últimos dois caracteres da String, exatamente o último OR
            $filtro_processos=substr($filtro_processos,0,-2).') ';
        }
        //se o usuário for uma instituição, só pode ver os processos que ela está atrelada
        if(isInstituicao()){
            $filtro_processos=' AND ( p.idusuario = '.$_SESSION['USUARIO']['idusuario'].' )';
        }
        //variaveis de controle para as buscas
        $busca_juncoes='';
        $busca_where='';
        //verifica se EXISTE UMA BUSCA e descobre o tipo
        if($busca!==NULL){
            //verifica o tipo de busca
            $aux = explode(APP_LINE_BREAK, $busca);
            //se tamanho maior que 1 é por entidade OU por responsável
            if(sizeof($aux)>1){
                //se for por entidade
                if(isset($aux[2]) && $aux[2]=="entidade"){
                    //entidade_nome
                    if(!empty($aux[0])){
                        $busca_where.=' AND u.nome_instituicao like \'%'.$aux[0].'%\'';
                    }
                    //entidade_cidade
                    if(!empty($aux[1])){
                        $busca_where.=' AND u.idmunicipio = \''.$aux[1].'\'';
                    }
                    //se for por responsável
                }elseif(isset($aux[1]) && $aux[1]=="responsavel"){
                    //se for um nome válido, atribui a variavel responsavel
                    if(strlen($aux[0])>1){
                        $b_responsavel=trim($aux[0]);
                        //do contrario, coloca valor "-" para zerar variavel
                    }else{
                        $b_responsavel="-";
                    }
                    //limita-se a exibição dos processos com responsável compativel com a busca
                    //inicializa o filtro de processos para o responsável
                    $filtro_processos.=' AND (p.idprocesso=0 OR';
                    //varre array de processos-responsaveis para ver se encontra processos com o responsavel informado
                    //define que é uma var global para poder trabalhar com seus dados
                    global $array_responsaveis;
                    //MUITA ATENÇÃO, AQUI IMAGINAMOS QUE O IDMAXIMO DE IDPROCESSO SERA 999.999
                    for($i=1;$i<=999999;$i++){
                        //se achar uma posição válida de IDPROCESSO
                        if(isset($array_responsaveis[$i]) && !empty($array_responsaveis[$i])){
                            //varre esse array procurando pelo responsável desejado
                            foreach ($array_responsaveis[$i] as $p) {
                                foreach ($p as $fnomeusuario) {
                                    //se encontrar o usuário no array de responsaveis, adiciona esse idprocesso à SQL
                                    if(strpos(textoMaiusculo($fnomeusuario), textoMaiusculo($b_responsavel))!==false){
                                        $filtro_processos.=' p.idprocesso='.$i.' OR';
                                    }
                                }
                            }
                        }
                    }
                    //remove o último ' OR' da string para deixar SQL ok
                    $filtro_processos=substr($filtro_processos,0,-2).")";
                }
                //caso contrário é por número do processo
            }else{
                $numero=$busca;
                //adiciona clausulas a SQL
                $busca_juncoes='';
                $busca_where='AND p.numero like \'%'.$numero.'%\' ';
            }
        }//fim if EXISTE BUSCA
        $sql='  SELECT MAX(ep.idetapa_processo) as ultimaalteracao, p.idprocesso,p.numero, u.nome_instituicao,
                e.nome as nomeetapa, e.ordem, e.bloquear,
                m.nome as nomemunicipio, p.dtprazo
                FROM processo p
                INNER JOIN etapa e ON e.idetapa=p.idetapa
                INNER JOIN usuario u ON u.idusuario=p.idusuario
                INNER JOIN municipio m ON m.idmunicipio=u.idmunicipio
                INNER JOIN etapa_processo ep ON p.idprocesso=ep.idprocesso
                '.$busca_juncoes.'
                WHERE p.flag=1 AND e.nome != "Processo encerrado" '.$filtro_processos.' '.$busca_where.' 
                GROUP BY ep.idprocesso
                ORDER BY '.$orderby;
        try {
            //se foi setado para exibir todos os registros da consulta atual, define-se:
            if(isset($_GET["showAllRecords"]) && $_GET["showAllRecords"]==true){
                $query = $this->conn->query($sql.' LIMIT 0,9999999999');
            }else{
                //consulta normal (paginada)
                $query = $this->conn->query($sql.' LIMIT '.$paginacao_inicio.','.APP_MAX_PAGE_ROWS);
            }
            //consulta sem paginacao (visando pegar o total de registros)
            $query_total=$this->conn->query($sql);
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
            $result[0]["paginacao_numlinhas"] = $query_total->rowCount();

        }catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;
    }

    /* Função para retornar todos os processos encerrados */
    public function processoEncerrados($paginacao_inicio, $busca=NULL, $order=NULL, $ascdesc=NULL,$arrayStatus=NULL,$arrayOrigem=NULL) {
        $result=false;
        $orderby="ultimaalteracao DESC";
        if($order!=NULL && $ascdesc!=NULL){
            if($order=="numero"){
                $orderby="p.numero ".$ascdesc;
            }
            if($order=="etapa"){
                $orderby="e.ordem ".$ascdesc;
            }
            if($order=="instituicao"){
                $orderby="u.nome_instituicao ".$ascdesc;
            }
            if($order=="municipio"){
                $orderby="m.nome ".$ascdesc;
            }
            if($order=="dtprazo"){
                $orderby="p.dtprazo ".$ascdesc;
            }
        }
        //variavel que ira adicionar restrições de exibição de processo, ou não..
        $filtro_processos='';
        //se o usuário tiver restrição de processos:
        if(isset($_SESSION['USUARIO']['processos'])){
            //inicializa o filtro
            $filtro_processos=' AND ( ';
            //para cada processo encontrado, adicione ele como SQL
            for($i=0;$i<sizeof($_SESSION['USUARIO']['processos']);$i++){
                $filtro_processos.=' p.idprocesso='.$_SESSION['USUARIO']['processos'][$i].' OR';
            }
            //ao final do loop remove os últimos dois caracteres da String, exatamente o último OR
            $filtro_processos=substr($filtro_processos,0,-2).') ';
        }
        //se o usuário for uma instituição, só pode ver os processos que ela está atrelada
        if(isInstituicao()){
            $filtro_processos=' AND ( p.idusuario = '.$_SESSION['USUARIO']['idusuario'].' )';
        }
        //variaveis de controle para as buscas
        $busca_juncoes='';
        $busca_where='';
        //verifica se EXISTE UMA BUSCA e descobre o tipo
        if($busca!==NULL){
            //verifica o tipo de busca
            $aux = explode(APP_LINE_BREAK, $busca);
            //se tamanho maior que 1 é por entidade OU por responsável
            if(sizeof($aux)>1){
                //se for por entidade
                if(isset($aux[2]) && $aux[2]=="entidade"){
                    //entidade_nome
                    if(!empty($aux[0])){
                        $busca_where.=' AND u.nome_instituicao like \'%'.$aux[0].'%\'';
                    }
                    //entidade_cidade
                    if(!empty($aux[1])){
                        $busca_where.=' AND u.idmunicipio = \''.$aux[1].'\'';
                    }
                    //se for por responsável
                }elseif(isset($aux[1]) && $aux[1]=="responsavel"){
                    //se for um nome válido, atribui a variavel responsavel
                    if(strlen($aux[0])>1){
                        $b_responsavel=trim($aux[0]);
                        //do contrario, coloca valor "-" para zerar variavel
                    }else{
                        $b_responsavel="-";
                    }
                    //limita-se a exibição dos processos com responsável compativel com a busca
                    //inicializa o filtro de processos para o responsável
                    $filtro_processos.=' AND (p.idprocesso=0 OR';
                    //varre array de processos-responsaveis para ver se encontra processos com o responsavel informado
                    //define que é uma var global para poder trabalhar com seus dados
                    global $array_responsaveis;
                    //MUITA ATENÇÃO, AQUI IMAGINAMOS QUE O IDMAXIMO DE IDPROCESSO SERA 999.999
                    for($i=1;$i<=999999;$i++){
                        //se achar uma posição válida de IDPROCESSO
                        if(isset($array_responsaveis[$i]) && !empty($array_responsaveis[$i])){
                            //varre esse array procurando pelo responsável desejado
                            foreach ($array_responsaveis[$i] as $p) {
                                foreach ($p as $fnomeusuario) {
                                    //se encontrar o usuário no array de responsaveis, adiciona esse idprocesso à SQL
                                    if(strpos(textoMaiusculo($fnomeusuario), textoMaiusculo($b_responsavel))!==false){
                                        $filtro_processos.=' p.idprocesso='.$i.' OR';
                                    }
                                }
                            }
                        }
                    }
                    //remove o último ' OR' da string para deixar SQL ok
                    $filtro_processos=substr($filtro_processos,0,-2).")";
                }
                //caso contrário é por número do processo
            }else{
                $numero=$busca;
                //adiciona clausulas a SQL
                $busca_juncoes='';
                $busca_where='AND p.numero like \'%'.$numero.'%\' ';
            }
        }//fim if EXISTE BUSCA
        $sql='  SELECT MAX(ep.idetapa_processo) as ultimaalteracao, p.idprocesso,p.numero, u.nome_instituicao,
                e.nome as nomeetapa, e.ordem, e.bloquear,
                m.nome as nomemunicipio, p.dtprazo
                FROM processo p
                INNER JOIN etapa e ON e.idetapa=p.idetapa
                INNER JOIN usuario u ON u.idusuario=p.idusuario
                INNER JOIN municipio m ON m.idmunicipio=u.idmunicipio
                INNER JOIN etapa_processo ep ON p.idprocesso=ep.idprocesso
                '.$busca_juncoes.'
                WHERE p.flag=1 AND e.nome = "Processo encerrado" '.$filtro_processos.' '.$busca_where.' 
                GROUP BY ep.idprocesso
                ORDER BY '.$orderby;
        try {
            //se foi setado para exibir todos os registros da consulta atual, define-se:
            if(isset($_GET["showAllRecords"]) && $_GET["showAllRecords"]==true){
                $query = $this->conn->query($sql.' LIMIT 0,9999999999');
            }else{
                //consulta normal (paginada)
                $query = $this->conn->query($sql.' LIMIT '.$paginacao_inicio.','.APP_MAX_PAGE_ROWS);
            }
            //consulta sem paginacao (visando pegar o total de registros)
            $query_total=$this->conn->query($sql);
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
            $result[0]["paginacao_numlinhas"] = $query_total->rowCount();

        }catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;
    }

    public function getInfosCapa(Processo $Processo){
        $result=false;
        try {
            $sql= '   SELECT p.idprocesso,p.idprocessotipo,p.idetapa,p.idusuario,p.numero, p.dtescolhida, p.obsposse, p.dtposse1, p.dtposse2, p.dtposse3,p.dtprazo,
                e.msgadd,e.msgcapa,e.escolhedata, 
                p.nomepresidentecee as nomepresidentecee, 
                p.nomesecretariocee as nomesecretariocee,
                
                t.nome as nometipo,
                e.nome as nomeetapa, e.ordem as ordemetapa,
                e.bloquear, e.expira,
                u.nome as nomeresponsavel, u.email1, u.email2, u.celular,u.telefone, u.nome_instituicao, u.dtexpiracao, u.login, u.idsubsecao,
                m.nome as nomecidade,
                s.nome as nomesubsecao, sm.nome as nomecidadesubsecao
                FROM processo p
                INNER JOIN processotipo t ON t.idprocessotipo=p.idprocessotipo
                INNER JOIN etapa e ON e.idetapa=p.idetapa
                INNER JOIN usuario u ON u.idusuario=p.idusuario
                INNER JOIN municipio m ON m.idmunicipio=u.idmunicipio
                INNER JOIN subsecao_municipio ms ON ms.idmunicipio=u.idmunicipio
                INNER JOIN subsecao s ON s.idsubsecao=ms.idsubsecao
                INNER JOIN municipio sm ON sm.idmunicipio=s.idmunicipio
                
                WHERE p.idprocesso = ? and p.flag = '.APP_FLAG_ACTIVE;
            $query = $this->conn->prepare($sql);
            $query->bindValue(1, $Processo->getId(), PDO::PARAM_INT);
            $query->execute();
            $result = $query->fetch(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;

    }
   
    public function insert(Processo $Processo) {
        $inseriu=true;
        $this->conn->beginTransaction();
        try {
            $query = $this->conn->prepare(
                'INSERT INTO processo
                 (  idprocesso, idusuario, idprocessotipo, idetapa,
                    numero, dtcriacao, dtescolhida,modo,militar,nomepresidentecee, nomesecretariocee,
                    flag) 
                 VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)'
            );

            $query->bindValue(1, NULL, PDO::PARAM_INT);//idprocesso é AUTO_INCREMENT, então NULL
            $query->bindValue(2, $Processo->getUsuario(), PDO::PARAM_INT);
            $query->bindValue(3, $Processo->getProcessoTipo(), PDO::PARAM_INT);
            $query->bindValue(4, $Processo->getEtapa(), PDO::PARAM_INT);
            $query->bindValue(5, $Processo->getNumero(), PDO::PARAM_STR);
            $query->bindValue(6, $Processo->getDtEscolhida(), PDO::PARAM_INT);
            $query->bindValue(7, $Processo->getModo(), PDO::PARAM_INT);
            $query->bindValue(8, $Processo->getMilitar(), PDO::PARAM_INT);
            $query->bindValue(9, $Processo->getNomePresidenteCEE(), PDO::PARAM_STR);
            $query->bindValue(10, $Processo->getNomeSecretarioCEE(), PDO::PARAM_STR);
            $query->bindValue(11, APP_FLAG_ACTIVE, PDO::PARAM_INT);//flag ativo

            $query->execute();
            $inseriu = $this->conn->lastInsertId();
            $this->conn->commit();
        }
        catch(Exception $e) {
            $this->conn->rollback();
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
            $inseriu=false;
        }
        return $inseriu;
    }
 
 public function update(Processo $Processo) {
        $atualizou = true; 
        $this->conn->beginTransaction(); 
    
        try {
            $query = $this->conn->prepare(
                'UPDATE processo SET 
                    dtatualizacao = NOW(), 
                    idusuario = ?, 
                    idprocessotipo = ?, 
                    idetapa = ?, 
                    dtescolhida = ?, 
                    dtprazo = ?, 
                    dtfim = ?, 
                    dtaviso = ?, 
                    nomepresidentecee = ?, 
                    nomesecretariocee = ? 
                WHERE idprocesso = ?'
            );
    
            // Bind dos parâmetros
            $query->bindValue(1, $Processo->getUsuario(), PDO::PARAM_INT);
            $query->bindValue(2, $Processo->getProcessoTipo(), PDO::PARAM_INT);
            $query->bindValue(3, $Processo->getEtapa(), PDO::PARAM_INT);
            $query->bindValue(4, $Processo->getDtEscolhida(), PDO::PARAM_STR);
            $query->bindValue(5, $Processo->getPrazo(), PDO::PARAM_STR);
            $query->bindValue(6, $Processo->getDtFim(), PDO::PARAM_STR);
            $query->bindValue(7, $Processo->getDtAviso(), PDO::PARAM_STR);
            $query->bindValue(8, $Processo->getNomePresidenteCEE(), PDO::PARAM_STR); // Presidente
            $query->bindValue(9, $Processo->getNomeSecretarioCEE(), PDO::PARAM_STR); // Secretário
            $query->bindValue(10, $Processo->getId(), PDO::PARAM_INT);
    
            $query->execute();
            $this->conn->commit();
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log('Erro ao atualizar processo: ' . $e->getMessage());
            $atualizou = false;
    
            if (APP_SHOW_SQL_ERRORS) {
                echo var_dump($this) . '<hr>' . $e->getMessage();
                // exit();
            }
        }
    
        return $atualizou;
    }

    //retorna processos com DTFIM mas sem DTAVISO
    public function getDtFimSemDtAviso(){
        $result=false;
        try {
            $query = $this->conn->query(
                '   SELECT idprocesso,dtfim
                FROM processo
                WHERE dtfim > 0 AND (dtaviso IS NULL OR dtaviso = "0000-00-00 00:00:00") and flag = '.APP_FLAG_ACTIVE
            );
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;
    }

    //retorna processos com DTPRAZO mas sem FLAGPRAZO (não receberam e-mail)
    public function getDtPrazoSemDtAviso(){
        $result=false;
        try {
            $query = $this->conn->query(
                '   SELECT idprocesso, dtprazo
                FROM processo
                WHERE dtprazo > 0 AND idetapa <> '.ID_LAST_ETAPA.' AND flag = '.APP_FLAG_ACTIVE.' AND (flagprazo = 0 OR flagprazo IS NULL)');
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;

    }

    //da update na coluna DTAVISO de um processo
    public function updateDtAviso(Processo $Processo){
        $atualizou=true;
        $this->conn->beginTransaction();
        try {
            $query = $this->conn->prepare(
                'UPDATE processo SET dtaviso=CURRENT_TIMESTAMP WHERE idprocesso = ?'
            );
            $query->bindValue(1, $Processo->getId(), PDO::PARAM_INT);
            $query->execute();
            $this->conn->commit();
        }
        catch(Exception $e) {
            $this->conn->rollback();
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
            $atualizou=false;
        }

        return $atualizou;

    }

    //da update na coluna DTPRAZO de um processo
    public function updateFlagPrazo(Processo $Processo){
        $atualizou=true;
        $this->conn->beginTransaction();
        try {
            $query = $this->conn->prepare(
                'UPDATE processo SET flagprazo='.date("Ymd").' WHERE idprocesso = ?'
            );
            $query->bindValue(1, $Processo->getId(), PDO::PARAM_INT);
            $query->execute();
            $this->conn->commit();
        }
        catch(Exception $e) {
            $this->conn->rollback();
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
            $atualizou=false;
        }

        return $atualizou;

    }

    //da update na coluna DTPRAZO de um processo
    public function updateDtPrazo(Processo $Processo){
        $atualizou=true;
        $this->conn->beginTransaction();
        try {
            $sql = 'UPDATE processo SET dtprazo=?, flagprazo=0 WHERE idprocesso = ?';
            $query = $this->conn->prepare($sql);
            $query->bindValue(1, $Processo->getPrazo(), PDO::PARAM_INT);
            $query->bindValue(2, $Processo->getId(), PDO::PARAM_INT);
            $query->execute();
            $this->conn->commit();
        }
        catch(Exception $e) {
            $this->conn->rollback();
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
            $atualizou=false;
        }

        return $atualizou;

    }


    public function getTipos(){
        $result=false;
        try {
            $query = $this->conn->query(
                '   SELECT nome,idprocessotipo
                FROM processotipo
                WHERE flag = 1'
            );
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;

    }

    public function getNumero(){
        $result=false;
        try {
            $query = $this->conn->query(
                '   SELECT max(numero)+1 as numero
                FROM processo
                WHERE flag = 1'
            );
            $result = $query->fetch(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;

    }

    public function getOneTipo($idtipo){
        $result=false;
        try {
            $query = $this->conn->query(
                '   SELECT nome
                FROM processotipo
                WHERE idprocessotipo = '.$idtipo
            );
            $result = $query->fetch(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;

    }

    public function getLastFromInstituicao($idusuario){
        $result=false;
        try {
            $query = $this->conn->query(
                '   SELECT idprocesso
                FROM processo
                WHERE idusuario = '.$idusuario.' and flag = '.APP_FLAG_ACTIVE.
                '   ORDER BY idprocesso DESC
                LIMIT 0,1'
            );
            $result = $query->fetch(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;

    }

   

   
       
    

    public function updateModo(Processo $Processo) {
        $atualizou=true;
        $this->conn->beginTransaction();
        try {

            $query = $this->conn->prepare(
                '   UPDATE processo SET 
                    dtatualizacao = NOW(),modo = ?
                    WHERE idprocesso = ?');

            $query->bindValue(1, $Processo->getModo(), PDO::PARAM_INT);
            $query->bindValue(2, $Processo->getId(), PDO::PARAM_INT);

            $query->execute();
            $this->conn->commit();
        }
        catch(Exception $e) {
            $this->conn->rollback();
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
            $atualizou=false;
        }
        return $atualizou;
    }

    public function updateMilitar(Processo $Processo) {
        $atualizou=true;
        $this->conn->beginTransaction();
        try {

            $query = $this->conn->prepare(
                '   UPDATE processo SET 
                    dtatualizacao = NOW(),militar = ?
                    WHERE idprocesso = ?');

            $query->bindValue(1, $Processo->getMilitar(), PDO::PARAM_INT);
            $query->bindValue(2, $Processo->getId(), PDO::PARAM_INT);

            $query->execute();
            $this->conn->commit();
        }
        catch(Exception $e) {
            $this->conn->rollback();
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
            $atualizou=false;
        }
        return $atualizou;
    }

    public function setNomePresidenteCEE(Processo $Processo) {
        $atualizou = true;
        error_log("Iniciando setNomePresidenteCEE para Processo: $Processo com nome: $nomepresidentecee");
    
        try {
            $query = $this->conn->prepare(
                'UPDATE processo SET nomepresidentecee = ? WHERE idprocesso = ?'
            );
            $query->bindValue(1, trim($nomepresidentecee), PDO::PARAM_STR);
            $query->bindValue(2, $Processo, PDO::PARAM_INT);
            $query->execute();
            error_log("Atualização do nome do presidente concluída para Processo: $Processo");
        } catch (Exception $e) {
            error_log('Erro ao atualizar nome do presidente CEE: ' . $e->getMessage());
            $atualizou = false;
            if (APP_SHOW_SQL_ERRORS) {
                echo var_dump($this) . '<hr>' . $e->getMessage();
                exit();
            }
        }
    
        return $atualizou;
    }
    
    
    public function setNomeSecretarioCEE(Processo $Processo) {
        $atualizou = true;
        
        try {
            // Prepara a consulta SQL
            $query = $this->conn->prepare(
                'UPDATE processo SET nomesecretariocee = ? WHERE idprocesso = ?'
            );
    
            // Bind dos parâmetros
            $query->bindValue(1, trim($nomesecretariocee), PDO::PARAM_STR); // Remove espaços extras
            $query->bindValue(2, $Processo, PDO::PARAM_INT);
    
            // Executa a consulta
            $query->execute();
        } catch (Exception $e) {
            error_log('Erro ao atualizar nome do secretário CEE: ' . $e->getMessage());
            $atualizou = false;
            
            if (APP_SHOW_SQL_ERRORS) {
                echo var_dump($this) . '<hr>' . $e->getMessage();
                exit();
            }
        }
    
        return $atualizou;
    }
    

    public function updateDtEscolhida(Processo $Processo) {
        $atualizou=true;
        $this->conn->beginTransaction();
        try {

            $query = $this->conn->prepare(
                '   UPDATE processo SET 
                    dtatualizacao = NOW(), dtescolhida = ?, dtfim = ?, dtaviso = ?
                    WHERE idprocesso = ? and flag = '.APP_FLAG_ACTIVE);

            $query->bindValue(1, $Processo->getDtEscolhida(), PDO::PARAM_INT);
            $query->bindValue(2, $Processo->getDtFim(), PDO::PARAM_INT);
            $query->bindValue(3, $Processo->getDtAviso(), PDO::PARAM_INT);
            $query->bindValue(4, $Processo->getId(), PDO::PARAM_INT);

            $query->execute();
            $this->conn->commit();
        }
        catch(Exception $e) {
            $this->conn->rollback();
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
            $atualizou=false;
        }
        return $atualizou;
    }

    public function updateDtFim(Processo $Processo) {
        $atualizou=true;
        $this->conn->beginTransaction();
        try {

            $query = $this->conn->prepare(
                '   UPDATE processo SET 
                    dtfim = ?
                    WHERE idprocesso = ? and flag = '.APP_FLAG_ACTIVE);

            $query->bindValue(1, $Processo->getDtFim(), PDO::PARAM_INT);
            $query->bindValue(2, $Processo->getId(), PDO::PARAM_INT);

            $query->execute();
            $this->conn->commit();
        }
        catch(Exception $e) {
            $this->conn->rollback();
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
            $atualizou=false;
        }
        return $atualizou;
    }

    public function updateObsPosse(Processo $Processo) {
        $atualizou=true;
        $this->conn->beginTransaction();
        try {

            $query = $this->conn->prepare(
                '   UPDATE processo SET 
                    dtatualizacao = NOW(), obsposse = ?
                    WHERE idprocesso = ? and flag = '.APP_FLAG_ACTIVE);

            $query->bindValue(1, $Processo->getObsPosse(), PDO::PARAM_INT);
            $query->bindValue(2, $Processo->getId(), PDO::PARAM_INT);

            $query->execute();
            $this->conn->commit();
        }
        catch(Exception $e) {
            $this->conn->rollback();
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
            $atualizou=false;
        }
        return $atualizou;
    }


    public function delete($Processo){
        $deletou=true;
        $this->conn->beginTransaction();
        try {
            $query = $this->conn->prepare(
                'UPDATE processo SET  dtatualizacao=NOW(), flag=2 WHERE idprocesso=?'
            );
            $query->bindValue(1, $Processo->getId(), PDO::PARAM_INT);
            $query->execute();
            $this->conn->commit();
        }
        catch(Exception $e) {
            $this->conn->rollback();
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
            $deletou=false;
        }

        return $deletou;
    }

    /* Função para retornar todos os processos */
    public function getAll($somenteAtivos=NULL) {
        $result=false;
//      $this->conn->beginTransaction(); 
        try {
            if($somenteAtivos!=NULL){
                $clausula_where="";
            }else{
                $clausula_where=" WHERE p.flag=1 ";
            }

            $query = $this->conn->query(
                'SELECT p.idprocesso, p.numero FROM processo p '.$clausula_where.' ORDER BY p.numero DESC'
            );
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            $this->conn->rollback();
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;
    }

    public function getResponsaveisEtapa(Processo $Processo){
        $result=false;
        try {
            $sql= '
                SELECT ep.idperfil,u.nome,u.email1,u.email2
                FROM processo p
                INNER JOIN etapa_perfil ep ON p.idetapa=ep.idetapa
                INNER JOIN usuario u ON ep.idperfil=u.idperfil
                INNER JOIN etapa e ON p.idetapa=e.idetapa
                WHERE p.idprocesso = ? and u.flag = ? and p.flag = ? 
                and e.flag = ? and (u.email1 IS NOT NULL OR u.email2 IS NOT NULL)
                ORDER BY u.idperfil ASC';
            $query = $this->conn->prepare($sql);
            $query->bindValue(1, $Processo->getId(), PDO::PARAM_INT);
            $query->bindValue(2, APP_FLAG_ACTIVE, PDO::PARAM_INT);
            $query->bindValue(3, APP_FLAG_ACTIVE, PDO::PARAM_INT);
            $query->bindValue(4, APP_FLAG_ACTIVE, PDO::PARAM_INT);
            $query->execute();
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;

    }

    public function getPresidenteEtapa(Processo $Processo){
        $result=false;
        try {
            $sql= '
                SELECT ep.idperfil,u.nome,u.email1,u.email2
                FROM processo p
                INNER JOIN etapa_perfil ep ON p.idetapa=ep.idetapa
                INNER JOIN usuario u ON ep.idperfil=u.idperfil
                INNER JOIN etapa e ON p.idetapa=e.idetapa
                WHERE p.idprocesso = ? and u.flag = ? and p.flag = ? 
                and e.flag = ? and (u.email1 IS NOT NULL OR u.email2 IS NOT NULL) and u.idperfil=21
                ORDER BY u.idperfil ASC';
            $query = $this->conn->prepare($sql);
            $query->bindValue(1, $Processo->getId(), PDO::PARAM_INT);
            $query->bindValue(2, APP_FLAG_ACTIVE, PDO::PARAM_INT);
            $query->bindValue(3, APP_FLAG_ACTIVE, PDO::PARAM_INT);
            $query->bindValue(4, APP_FLAG_ACTIVE, PDO::PARAM_INT);
            $query->execute();
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;

    }

    public function getSecretarioEtapa(Processo $Processo){
        $result=false;
        try {
            $sql= '
                SELECT ep.idperfil,u.nome,u.email1,u.email2
                FROM processo p
                INNER JOIN etapa_perfil ep ON p.idetapa=ep.idetapa
                INNER JOIN usuario u ON ep.idperfil=u.idperfil
                INNER JOIN etapa e ON p.idetapa=e.idetapa
                WHERE p.idprocesso = ? and u.flag = ? and p.flag = ? 
                and e.flag = ? and (u.email1 IS NOT NULL OR u.email2 IS NOT NULL) and u.idperfil=22
                ORDER BY u.idperfil ASC';
            $query = $this->conn->prepare($sql);
            $query->bindValue(1, $Processo->getId(), PDO::PARAM_INT);
            $query->bindValue(2, APP_FLAG_ACTIVE, PDO::PARAM_INT);
            $query->bindValue(3, APP_FLAG_ACTIVE, PDO::PARAM_INT);
            $query->bindValue(4, APP_FLAG_ACTIVE, PDO::PARAM_INT);
            $query->execute();
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;

    }

    public function getFiscaisSubsecao($idsubsecao){
        $result=false;
        try {
            $sql= '
                SELECT u.nome,u.email1,u.email2
                FROM usuario u
                WHERE u.idsubsecao = ? AND u.flag = ? AND u.idperfil = ?
                AND (u.email1 IS NOT NULL OR u.email2 IS NOT NULL)';
            $query = $this->conn->prepare($sql);
            $query->bindValue(1, $idsubsecao, PDO::PARAM_INT);
            $query->bindValue(2, APP_FLAG_ACTIVE, PDO::PARAM_INT);
            $query->bindValue(3, PERFIL_IDFISCALIZACAO, PDO::PARAM_INT);
            $query->execute();
            $result = $query->fetchAll(PDO::FETCH_ASSOC);
        }
        catch(Exception $e) {
            if(APP_SHOW_SQL_ERRORS){
                echo var_dump($this).'<hr>'.$e->getMessage();exit();
            }
        }
        return $result;

    }


} ?>
