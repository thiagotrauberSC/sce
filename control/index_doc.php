<?php
require_once("../config.php");
require_once("../bin/errors.php");
require_once("../bin/functions.php");
require_once("../bin/js-css.php");
require_once("../login_verifica.php");

if(!empty($_REQUEST["p"])){

	$idprocesso=$_REQUEST["p"];

	if(verificaProcessoUsuario($idprocesso)){
	
	require_once("../menu_topo.php");	
		
	//carrega as bibliotecas para recuperar informações do BD
	require_once("../conexao.php");
	require_once('../model/Registry.php');
  require_once('../model/Etapa.php');
	require_once('../model/Processo.php');
  require_once('../model/Documento.php');
  require_once('../model/Usuario.php');
  require_once('../model/Historico.php');
 
 
  require_once('../dao/ProcessoDAO.php');
  require_once('../dao/UsuarioDAO.php');
  require_once('../dao/EtapaDAO.php');
  require_once('../dao/PerfilDAO.php');
  require_once('../dao/DocumentoDAO.php');
  require_once('../dao/HistoricoDAO.php');
  require_once('../dao/ResponsavelDAO.php');
 

  // Armazenar essa instância (conexão) no Registry - conecta uma só vez
  $registry = Registry::getInstance();
  $registry->set('Connection', $myBD);

  //verifica se é uma instituição, se for, redireciona para index_doc do seu último processo (caso não seja este o processo)
  if(isInstituicao()){
    $ProcessoDAO = new ProcessoDAO();
    $result = $ProcessoDAO->getLastFromInstituicao($_SESSION["USUARIO"]["idusuario"]);
    //verifica se o usuário NÃO tem um processo ativo
    if(!$result){
      enviaMsg("erro","Requisição negada","Seu usuário não possui um processo ativo atrelado.");
      echo "<meta http-equiv=\"refresh\" content=\"0; url=index.php\">";
      exit();
    //verifica se tem permissão e se já não está no processo que deve ir, se não estiver redireciona
    }elseif($result["idprocesso"]!=$idprocesso && verificaProcessoUsuario($result["idprocesso"])){
      echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=".$result["idprocesso"]."\">";
      exit();
    }
  }

  // Instanciar o processo
	$Processo = new Processo();
	$Processo->setId($idprocesso);
	// Instanciar o DAO e retornar infos da base
	$ProcessoDAO = new ProcessoDAO();
	$infosprocesso = $ProcessoDAO->getInfosCapa($Processo);

  // $Presidente = new Presidente();
  // $Presidente->setId($v);

  // $PresidenteDAO = new PresidenteDAO();
  // $infopresidente = $PresidenteDAO->getAllFrom($idprocesso);

  
// $infosecretario = $presidenteDAO->getSecretario($idprocesso);

  // define infos no objeto Processo
  $Processo->setMilitar($infosprocesso["militar"]);
  $Processo->setModo($infosprocesso["modo"]);
  
  

  //se não encontrar informações da capa, é pq o processo foi removido ou o link está incorreto
  if(!$infosprocesso){
    enviaMsg("erro","Acesso negado","Sem permissão de acesso, link quebrado ou os dados estão inválidos");
    echo "<meta http-equiv=\"refresh\" content=\"0; url=index_pro.php\">";
    exit();
  }
  //se encontrar a coluna bloquear com o valor de BLOQUEIO, impede modificações
  if($infosprocesso["bloquear"]==ETAPA_BLOQUEIA_PROCESSO){
    $bloquearProcesso=true;
  }else{
    $bloquearProcesso=false;
  }


  // Instanciar DAO responsáveis e retornar infos
  $ResponsavelDAO = new ResponsavelDAO();

  $responsaveis = $ResponsavelDAO->getAllFrom($idprocesso);
  
  // $PresidenteDAO = new PresidenteDAO();

  // $presidentes = $PresidenteDAO->getPresidente($idprocesso);

  // $SecretarioDAO = new SecretarioDAO();

  // $secretarios = $SecretarioDAO->getSecretario($idprocesso);

  //pega infos da etapa atual do processo
  $Etapa = new Etapa();
  $Etapa->setId($infosprocesso["idetapa"]);
  //pega os documentos que são atrelados a essa etapa
  $EtapaDAO = new EtapaDAO();
  $numdocs=$EtapaDAO->getTiposDocumentos($Etapa);
  $infosetapa = $EtapaDAO->getOne($infosprocesso["idetapa"]);
  $arrayEtapas = $EtapaDAO->getAll();

  //verifica se o usuário atual pode fazer ações nesta etapa
  $etapaperfis = $EtapaDAO->getEtapaPerfis($Etapa);
  $etapaperfisarray = array();
  $etapaperfisnomesarray = array();
  //prepara DAO para consultar informações dos Perfis
  $PerfilDAO = new PerfilDAO();
  for($i=0;$i<sizeof($etapaperfis);$i++){     
    $etapaperfisarray[]=$etapaperfis[$i]["idperfil"];    
    $dadosperfil=$PerfilDAO->getOne($etapaperfis[$i]["idperfil"]);
    $etapaperfisnomesarray[]["nomeperfil"]=$dadosperfil["nome"];
  }
  //se o usuário pode efetuar ações nesta etapa ( se ele for do perfil responsavel e etapa da Comissão de Ética ele também efetua ações) OU se é admin 
  if( in_array($_SESSION["USUARIO"]["idperfil"],$etapaperfisarray) || isAdmin() || ($_SESSION["USUARIO"]["idperfil"]==PERFIL_IDRESPONSAVEL && in_array(PERFIL_IDCOMISSAOETICA,$etapaperfisarray) ) ){
    $usuarioEfetuaAcoes=true;
  }else{
    $usuarioEfetuaAcoes=false;
  }

  //SUBMENU DE AÇÕES DA CAPA DO PROCESSO / DOCUMENTO
  require_once('submenu_doc.php');
  //FIM SUBMENU DE AÇÕES  

  // Instanciar o DAO e retornar infos da base
  $DocumentoDAO = new DocumentoDAO();
  if(isset($_GET["order"]) && ($_GET["order"]=="dtenvio" || $_GET["order"]=="nomedocumento" || $_GET["order"]=="nomeusuario")&&($_GET["ascdesc"]=="ASC" || $_GET["ascdesc"]=="DESC") ){
    $documentos = $DocumentoDAO->getAllFromProcesso($Processo, $_GET["order"], $_GET["ascdesc"]);
  }else{
    $documentos = $DocumentoDAO->getAllFromProcesso($Processo);
  }

  //aqui faz modificações "auxiliares" requisitadas pelas etapas  
  
    //se essa etapa exigir uma expiração do login do usuário
      if($infosetapa["expira"]>0){
        //pega dados da ultima etapa em etapa_processo
        $dadosEtapaAnterior=$EtapaDAO->getLastEtapaProcesso($Processo);
          //se for o dia que foi criada a etapa e a etapa não tiver sido atualizada, insere a expiração no login do usuário
          if($dadosEtapaAnterior["dtcriacao"]==date("Ymd") && empty($dadosEtapaAnterior["dtatualizacao"])){
            //calcula data da expiracao do acesso
            $timestamp = strtotime("+".$infosetapa["expira"]." day");
            $dtexpiracao = date('Ymd', $timestamp);
            //insere limitação
            $Usuario = new Usuario();
            $Usuario->setId($infosprocesso["idusuario"]);
            $Usuario->setDtExpiracao($dtexpiracao);
            $UsuarioDAO = new UsuarioDAO();
            $inseriuExpiracao = $UsuarioDAO->insertExpiracao($Usuario);
            //após limitar o acesso do usuário, atualiza etapa_processo
            $EtapaAtualizada = new Etapa();
            $EtapaAtualizada->setId($dadosEtapaAnterior["idetapa_processo"]);
            $EtapaAtualizada->setUsuario2($_SESSION["USUARIO"]["idusuario"]);
            $EtapaAtualizada->setAprovaMsg($dadosEtapaAnterior["aprovacaomsg"]);
            $EtapaAtualizada->setAprova($dadosEtapaAnterior["aprovacao"]);
            $atualizouEtapaProcesso = $EtapaDAO->updateEtapaProcesso($EtapaAtualizada);
          }
      }

  
  
  //post das ações que é só envio da Lista de Candidados ao Pleito ou "não houve candidatos"
  if($usuarioEfetuaAcoes && !$bloquearProcesso && isset($_POST["candidatos"]) && !empty($_POST["candidatos"])){

    //se houve candidatos = COM eleições
    if($_POST["candidatos"]=="com"){

      //se usuário não enviou LISTA DE CANDIDATOS, avisa do erro!
      if(!isset($_FILES['userfile']) || empty($_FILES['userfile']['name'])){
        enviaMsg("erro","Dados incompletos","Se houver candidatos, é preciso enviar a Lista de Pessoas Inscritas. Por favor, refaça a etapa.");
          echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
          exit();
      }
      //SE HOUVER ARQUIVO PARA SER ENVIADO
      if(isset($_FILES['userfile']) && !empty($_FILES['userfile']['name'])){
        //SE o arquivo estiver numa extensão aceita:
        if( 
            !verificaExtensaoArquivo($_FILES['userfile']['name'],'doc')
          &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'docx')
          &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'odt')
          &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'pdf')
          &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'xls')
          &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'xlsx')
        ){
          //arquivo no formato incorreto
          enviaMsg("erro","Formato de arquivo inválido","A Lista de Pessoas Inscritas precisa ser no formato PDF, DOC, DOCX, XLS, XLSX ou ODT");
          echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
          exit();

        }//fim if "extensão INcorreta do arquivo" = daqui pra baixo a extensão está correta
        else{

          //envia o documento
          $caminho = APP_URL_UPLOAD;
          $pasta = $idprocesso.'/';
          //se não existir, cria a pasta
          if(!file_exists($caminho.$pasta)){
            mkdir($caminho.$pasta,0774);
          }
          $link = codifica(mt_rand(1,99).time().$idprocesso).".".retornaExtensaoArquivo($_FILES['userfile']['name']);
          $destino = $caminho.$pasta.$link;
          //envia arquivo para o servidor
          if(move_uploaded_file($_FILES['userfile']['tmp_name'],$destino)){
            //define o arquivo como 664
            chmod($destino,0664);
          }else{
            //não enviou o arquivo
            enviaMsg("erro","Não aprovação / aprovação não efetuada","O arquivo não pôde ser enviado para o servidor");
            echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
            exit();
          }

          //add infos do documento no Banco de Dados
          $Documento = new Documento();
          $Documento->setProcesso($idprocesso);
          $Documento->setUsuario($_SESSION["USUARIO"]["idusuario"]);
          $Documento->setDocumentoTipo(DOC_IDLISTAINSCRITOS);
          $Documento->setLink($link);
          $Documento->setObs('');
          $DocumentoDAO = new DocumentoDAO(); 
          $iddocumento = $DocumentoDAO->insert($Documento);



          //problema ao enviar documento
          if(!$iddocumento || $iddocumento===false || $iddocumento==false){
            enviaMsg("erro","Documento não inserido","O documento não pôde ser registrado no banco de dados");
            echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
            exit();
          //documento enviado com sucesso
          }else{


            //GRAVAR ENVIO DO DOCUMENTO NO LOG
            $Historico = new Historico();
            $Historico->setAcao(LOG_ADD_DOC);
            $Historico->setProcesso($idprocesso);  
            $Historico->setDocumento($iddocumento);
            $Historico->setObs(NULL);
            $HistoricoDAO = new HistoricoDAO();
            $inseriuLog=$HistoricoDAO->insert($Historico);

            //atualiza processo para informar que HAVERÁ eleições
            $Processo->setModo(PROCESSOETAPA_COMELEICOES);
            $atualizouModo = $ProcessoDAO->updateModo($Processo);
            
            //avança para a primeira etapa com eleições
              //recupera dados da etapa atual
              $dadosEtapaAtual = $EtapaDAO->getLastEtapaProcesso($Processo);

            //instancia a etapa que sera a proxima
            $ProximaEtapa = new Etapa();
            //Recebe informações da próxima etapa
            $ProximaEtapa = proximaEtapaProcesso2($arrayEtapas,$dadosEtapaAtual,$Processo);

            //se chegou a uma próxima etapa válida
            if($ProximaEtapa->getId() > 0){
              //insere nova etapa no histórico do processo
              $inseriuEtapaProcesso = $EtapaDAO->insertEtapaProcesso($ProximaEtapa);
              //atualiza processo
              $atualizouEtapa = $EtapaDAO->updateEtapa($ProximaEtapa);
              //atualiza prazo da etapa do processo
              //calcula o prazo de acordo com o valor em dias que vier do getPrazo
              if($ProximaEtapa->getPrazo()>0){
                $dtprazo=date('Ymd', strtotime('+'.$ProximaEtapa->getPrazo().' days'));
              }else{
                $dtprazo=0;
              }             
              $Processo->setPrazo($dtprazo);
              $atualizouDtPrazo = $ProcessoDAO->updateDtPrazo($Processo);
            }

            //GRAVAR DADOS NO LOG
            $Historico = new Historico();
            $Historico->setAcao(LOG_UPDATE_PRO);
            $Historico->setProcesso($idprocesso);  
            $Historico->setDocumento($iddocumento);
            $Historico->setObs("Modo escolhido: Com eleições");
            $HistoricoDAO = new HistoricoDAO();
            $inseriuLog=$HistoricoDAO->insert($Historico);

            //se chegar aqui = sucesso
            enviaMsg("sucesso","Parabéns","Etapa concluída");
            echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
            exit();
          }

        }//fim else do if "extensãocorreta"

      }

    }//fim IF == com eleições


    //se NÃO houve candidatos = SEM eleições
    if($_POST["candidatos"]=="sem"){
      
      //atualiza processo para informar que NÃO HAVERÁ eleições
      $Processo->setModo(PROCESSOETAPA_SEMELEICOES);
      $atualizouModo = $ProcessoDAO->updateModo($Processo);

      //recupera dados da etapa atual
      $dadosEtapaAtual = $EtapaDAO->getLastEtapaProcesso($Processo);

      //instancia a etapa que sera a proxima
      $ProximaEtapa = new Etapa();
      //Recebe informações da próxima etapa
      $ProximaEtapa = proximaEtapaProcesso2($arrayEtapas,$dadosEtapaAtual,$Processo);

      //se chegou a uma próxima etapa válida
      if($ProximaEtapa->getId() > 0){
        //insere nova etapa no histórico do processo
        $inseriuEtapaProcesso = $EtapaDAO->insertEtapaProcesso($ProximaEtapa);
        //atualiza processo
        $atualizouEtapa = $EtapaDAO->updateEtapa($ProximaEtapa);
        //atualiza prazo da etapa do processo
        //calcula o prazo de acordo com o valor em dias que vier do getPrazo
        if($ProximaEtapa->getPrazo()>0){
          $dtprazo=date('Ymd', strtotime('+'.$ProximaEtapa->getPrazo().' days'));
        }else{
          $dtprazo=0;
        }             
        $Processo->setPrazo($dtprazo);
        $atualizouDtPrazo = $ProcessoDAO->updateDtPrazo($Processo);
      }

      //GRAVAR DADOS NO LOG
      $Historico = new Historico();
      $Historico->setAcao(LOG_UPDATE_PRO);
      $Historico->setProcesso($idprocesso);
      $Historico->setDocumento(NULL);
      $Historico->setObs("Modo escolhido: Sem eleições");
      $HistoricoDAO = new HistoricoDAO();
      $inseriuLog=$HistoricoDAO->insert($Historico);

      //se chegar aqui = sucesso
      enviaMsg("sucesso","Parabéns","Etapa concluída");
      echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
      exit();

    }

  }//fim IF candidatos

  //post das ações que é só envio do Registro de recursos e respostas da Comissão Eleitoral ou "não houve recursos/questionamentos"
  if($usuarioEfetuaAcoes && !$bloquearProcesso && isset($_POST["recursos"]) && !empty($_POST["recursos"])){

    //se houve recursos = COM recursos
    if($_POST["recursos"]=="com"){

      //se usuário não enviou LISTA DE CANDIDATOS, avisa do erro!
      if(!isset($_FILES['userfile']) || empty($_FILES['userfile']['name'])){
        enviaMsg("erro","Dados incompletos","Se houver recursos, é preciso enviar o Registro de recursos e respostas da Comissão Eleitoral. Por favor, refaça a etapa.");
          echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
          exit();
      }
      //SE HOUVER ARQUIVO PARA SER ENVIADO
      if(isset($_FILES['userfile']) && !empty($_FILES['userfile']['name'])){
        //SE o arquivo estiver numa extensão aceita:
        if( 
            !verificaExtensaoArquivo($_FILES['userfile']['name'],'doc')
          &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'docx')
          &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'odt')
          &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'pdf')
          &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'xls')
          &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'xlsx')
        ){
          //arquivo no formato incorreto
          enviaMsg("erro","Formato de arquivo inválido","O Registro de recursos e respostas da Comissão Eleitoral precisa ser no formato PDF, DOC, DOCX, XLS, XLSX ou ODT");
          echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
          exit();

        }//fim if "extensão INcorreta do arquivo" = daqui pra baixo a extensão está correta
        else{

          //envia o documento
          $caminho = APP_URL_UPLOAD;
          $pasta = $idprocesso.'/';
          //se não existir, cria a pasta
          if(!file_exists($caminho.$pasta)){
            mkdir($caminho.$pasta,0774);
          }
          $link = codifica(mt_rand(1,99).time().$idprocesso).".".retornaExtensaoArquivo($_FILES['userfile']['name']);
          $destino = $caminho.$pasta.$link;
          //envia arquivo para o servidor
          if(move_uploaded_file($_FILES['userfile']['tmp_name'],$destino)){
            //define o arquivo como 664
            chmod($destino,0664);
          }else{
            //não enviou o arquivo
            enviaMsg("erro","Tente novamente","O arquivo não pôde ser enviado para o servidor");
            echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
            exit();
          }

          //add infos do documento no Banco de Dados
          $Documento = new Documento();
          $Documento->setProcesso($idprocesso);
          $Documento->setUsuario($_SESSION["USUARIO"]["idusuario"]);
          $Documento->setDocumentoTipo(DOC_IDRECURSOS);
          $Documento->setLink($link);
          $Documento->setObs('');
          $DocumentoDAO = new DocumentoDAO(); 
          $iddocumento = $DocumentoDAO->insert($Documento);

          //problema ao enviar documento
          if(!$iddocumento || $iddocumento===false || $iddocumento==false){
            enviaMsg("erro","Documento não inserido","O documento não pôde ser registrado no banco de dados");
            echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
            exit();
          //documento enviado com sucesso
          }else{

            //recupera dados da etapa atual
            $dadosEtapaAtual = $EtapaDAO->getLastEtapaProcesso($Processo);
            //instancia a etapa que sera a proxima
            $ProximaEtapa = new Etapa();
            //Recebe informações da próxima etapa
            $ProximaEtapa = proximaEtapaProcesso2($arrayEtapas,$dadosEtapaAtual,$Processo);
            //se chegou a uma próxima etapa válida
            if($ProximaEtapa->getId() > 0){
              //insere nova etapa no histórico do processo
              $inseriuEtapaProcesso = $EtapaDAO->insertEtapaProcesso($ProximaEtapa);
              //atualiza processo
              $atualizouEtapa = $EtapaDAO->updateEtapa($ProximaEtapa);
              //atualiza prazo da etapa do processo
              //calcula o prazo de acordo com o valor em dias que vier do getPrazo
              if($ProximaEtapa->getPrazo()>0){
                $dtprazo=date('Ymd', strtotime('+'.$ProximaEtapa->getPrazo().' days'));
              }else{
                $dtprazo=0;
              }             
              $Processo->setPrazo($dtprazo);
              $atualizouDtPrazo = $ProcessoDAO->updateDtPrazo($Processo);
            }

            //GRAVAR ENVIO DO DOCUMENTO NO LOG
            $Historico = new Historico();
            $Historico->setAcao(LOG_ADD_DOC);
            $Historico->setProcesso($idprocesso);  
            $Historico->setDocumento($iddocumento);
            $Historico->setObs('Houve recursos e respostas da Comissão Eleitoral quanto ao resultado das eleições');
            $HistoricoDAO = new HistoricoDAO();
            $inseriuLog=$HistoricoDAO->insert($Historico);

            //se chegar aqui = sucesso
            enviaMsg("sucesso","Parabéns","Etapa concluída");
            echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
            exit();
          }

        }//fim else do if "extensãocorreta"

      }// fim if SE HOUVER ARQUIVO

    }//fim IF == com eleições


    //se NÃO houve recursos = SEM recursos
    if($_POST["recursos"]=="sem"){

      //recupera dados da etapa atual
      $dadosEtapaAtual = $EtapaDAO->getLastEtapaProcesso($Processo);

      //instancia a etapa que sera a proxima
      $ProximaEtapa = new Etapa();
      //Recebe informações da próxima etapa
      $ProximaEtapa = proximaEtapaProcesso2($arrayEtapas,$dadosEtapaAtual,$Processo);

      //se chegou a uma próxima etapa válida
      if($ProximaEtapa->getId() > 0){
        //insere nova etapa no histórico do processo
        $inseriuEtapaProcesso = $EtapaDAO->insertEtapaProcesso($ProximaEtapa);
        //atualiza processo
        $atualizouEtapa = $EtapaDAO->updateEtapa($ProximaEtapa);
        //atualiza prazo da etapa do processo
        //calcula o prazo de acordo com o valor em dias que vier do getPrazo
        if($ProximaEtapa->getPrazo()>0){
          $dtprazo=date('Ymd', strtotime('+'.$ProximaEtapa->getPrazo().' days'));
        }else{
          $dtprazo=0;
        }             
        $Processo->setPrazo($dtprazo);
        $atualizouDtPrazo = $ProcessoDAO->updateDtPrazo($Processo);
      }

      //GRAVAR DADOS NO LOG
      //se inseriu o documento com sucesso => SALVAR NO HISTÓRICO
      $Historico = new Historico();
      $Historico->setAcao(LOG_UPDATE_PRO);
      $Historico->setProcesso($idprocesso);
      $Historico->setDocumento(NULL);
      $Historico->setObs('Não houve recursos e respostas da Comissão Eleitoral quanto ao resultado das eleições');
      $HistoricoDAO = new HistoricoDAO();
      $inseriuLog=$HistoricoDAO->insert($Historico);

      //se chegar aqui = sucesso
      enviaMsg("sucesso","Parabéns","Etapa concluída");
      echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
      exit();

    }

  }//fim IF recursos

  //post das ações que é só aprovação do documento
  if($usuarioEfetuaAcoes && !$bloquearProcesso && isset($_POST["aprova_doc"]) && !empty($_POST["aprova_doc"])){

    $aprovou = validaInteiro($_POST["aprova_doc"],1);
    $aprova = validaInteiro($_POST["aprova_doc"],1);
    if(isset($_POST["justificativa"]) && !empty($_POST["justificativa"])){
      //$justificativa=$_POST["justificativa"];
      $justificativa=nl2br($_POST["justificativa"]);
    }else{
      $justificativa=NULL;
    }

    //se aprovou o doc
    if($aprovou==ETAPA_APROVADA){

      //salva aprovação e a msg
          //carrega dados da última etapa do processo encontrado em ETAPA_PROCESSO
          $dadosEtapaAnterior=$EtapaDAO->getLastEtapaProcesso($Processo);
          //verifica se a última etapa de ETAPA_PROCESSO é a etapa atual do PROCESSO (consistência dos dados)
          if($dadosEtapaAnterior["idetapa"] != $infosprocesso["idetapa"]){
            //se não for, pega dados de todas etapas e compara com a etapa que está salva no processo
            $arrayEtapas = $EtapaDAO->getAll();
            foreach($arrayEtapas as $e){
              //quando encontrar o ID da etapa do processo, insere esta etapa como a última do etapa_processo
              if($e["idetapa"] == $infosprocesso["idetapa"]){
                $EtapaCorreta = new Etapa();
                $EtapaCorreta->setId($e["idetapa"]);
                $EtapaCorreta->setProcesso($idprocesso);
                $EtapaCorreta->setUsuario1($_SESSION["USUARIO"]["idusuario"]);
                $EtapaCorreta->setAprova($e["aprova"]);
                $EtapaCorreta->setAprovaMsg(NULL);
                //insere o ETAPA_PROCESSO
                $inseriuEtapaProcesso = $EtapaDAO->insertEtapaProcesso($EtapaCorreta);
                //sai do foreach
                break; 
              }
            }
          }

          //atualiza a etapa_processo com a última ação do usuário
          $dadosEtapaAnterior=$EtapaDAO->getLastEtapaProcesso($Processo);
          $Etapa->setId($dadosEtapaAnterior["idetapa_processo"]);
          $Etapa->setUsuario2($_SESSION["USUARIO"]["idusuario"]);
          $Etapa->setAprova($aprovou);
          $Etapa->setAprovaMsg(NULL);
          $atualizaEtapaProcesso = $EtapaDAO->updateEtapaProcesso($Etapa);
          //redefine o ID da etapa, pois logo abaixo é preciso saber se ela possui e-mails p/ enviar
          $Etapa->setId($infosprocesso["idetapa"]);

        //enviar e-mails
          $emailsetapa = $EtapaDAO->getEmails($Etapa);
          //se houver e-mails a enviar
          if(sizeof($emailsetapa)>0){
            //var de controle
            $emails = array();
            //para cada email 
            for($i=0;$i<sizeof($emailsetapa);$i++){
              if($emailsetapa[$i]["numero"] == 1){
                $emails[$i]["mensagem"]=$infosetapa["msgemail1"];
              }else{
                $emails[$i]["mensagem"]=$infosetapa["msgemail2"];
              }
              if(isset($emailsetapa[$i]["idperfil"]) && !empty($emailsetapa[$i]["idperfil"])){
                $emails[$i]["idperfil"]=$emailsetapa[$i]["idperfil"];
              }else{
                $emails[$i]["idperfil"]=NULL;
              }
              if(isset($emailsetapa[$i]["idusuario"]) && !empty($emailsetapa[$i]["idusuario"])){
                $emails[$i]["idusuario"]=$emailsetapa[$i]["idusuario"];
              }else{
                $emails[$i]["idusuario"]=NULL;
              }
              $emails[$i]["tipoemail"] = $emailsetapa[$i]["tipoemail"];
            }
            //define variaveis para o ajax_mail
            $tipoajaxmail="index_doc";
            $nomeprocesso='Processo de '.$infosprocesso["nometipo"].' nº '.$infosprocesso["numero"];
            $linkprocesso=APP_URL.'/control/index_doc.php?p='.$idprocesso;
            $processousuarioid=$infosprocesso["idusuario"];
            $PerfilDAO = new PerfilDAO();
            $dadosperfil=$PerfilDAO->getOne($_SESSION["USUARIO"]["idperfil"]);
            $nomeperfilusuario=$dadosperfil["nome"];
            //envia e-mails     
            require_once("ajax_mail.php");
          }

        //Atualizar historico da etapa atual (que passa agora a ser a anterior - a etapa do momento do envio do documento) em etapa_processo
          //depois de atualizar no banco, atualiza o array com dados da etapa anterior
          $dadosEtapaAnterior=$EtapaDAO->getLastEtapaProcesso($Processo);          

          //iniciar historico da nova etapa em etapa_processo
          $arrayEtapas = $EtapaDAO->getAll();

          //Objeto ETAPA para instanciar infos da proxima etapa
          $NovaEtapa = new Etapa();
          $NovaEtapa = proximaEtapaProcesso2($arrayEtapas,$dadosEtapaAnterior,$Processo);
          //$NovaEtapa = proximaEtapaProcesso($arrayEtapas,$dadosEtapaAnterior,$idprocesso);
          //se o ID da NovaEtapa >= 0 é possível trocar de etapa, então insere a nova etapa
          if($NovaEtapa->getId() >= 0){
            //insere nova etapa no histórico do processo
            $inseriuEtapaProcesso = $EtapaDAO->insertEtapaProcesso($NovaEtapa);
            //atualiza processo
            $atualizouEtapa = $EtapaDAO->updateEtapa($NovaEtapa);
            //atualiza prazo da etapa do processo
            //calcula o prazo de acordo com o valor em dias que vier do getPrazo
            if($NovaEtapa->getPrazo()>0){
              $dtprazo=date('Ymd', strtotime('+'.$NovaEtapa->getPrazo().' days'));
            }else{
              $dtprazo=0;
            }             
            $Processo->setPrazo($dtprazo);
            $atualizouDtPrazo = $ProcessoDAO->updateDtPrazo($Processo);
          }

        //salva histórico
          //GRAVAR DADOS NO LOG
          //define observações:
          //se inseriu o documento com sucesso => SALVAR NO HISTÓRICO
          $Historico = new Historico();
          $Historico->setAcao(LOG_UPDATE_PRO);
          $Historico->setProcesso($idprocesso);
          $Historico->setDocumento(0);
          $Historico->setObs("Documento aprovado - Etapa ".$infosetapa["ordem"]." ".$infosetapa["nome"]);
          $HistoricoDAO = new HistoricoDAO();
          $inseriuLog=$HistoricoDAO->insert($Historico);
          if(!$inseriuLog){
            enviaMsg("erro","O processo foi atualizado com erros","O histórico de aprovação não pôde ser salvo");
            echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
            exit();
          }

        //dá msg de sucesso
        enviaMsg("sucesso","Aprovação salva com sucesso");
        echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=".$idprocesso."\">";
        exit();


    //se não aprovou
    }else{

      //confere se apresentou uma justificativa mesmo
      if(!empty($justificativa)){

        //SE HOUVER ARQUIVO DE JUSTIFICATIVA A SER ENVIADO
        if(isset($_FILES['userfile']) && !empty($_FILES['userfile']['name'])){
          //SE o arquivo estiver numa extensão aceita:
          if( 
              !verificaExtensaoArquivo($_FILES['userfile']['name'],'doc')
            &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'docx')
            &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'odt')
            &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'pdf')
            &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'xls')
            &&  !verificaExtensaoArquivo($_FILES['userfile']['name'],'xlsx')
          ){
            //arquivo no formato incorreto
            enviaMsg("erro","Não aprovação / aprovação não efetuada","Os documentos enviados precisam ser no formato PDF, DOC, DOCX, XLS, XLSX ou ODT");
            echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
            exit();

          }//fim if "extensão INcorreta do arquivo" = daqui pra baixo a extensão está correta
          else{

            //envia o documento
            $caminho = APP_URL_UPLOAD;
            $pasta = $idprocesso.'/';
            //se não existir, cria a pasta
            if(!file_exists($caminho.$pasta)){
              mkdir($caminho.$pasta,0774);
            }
            $link = codifica(mt_rand(1,99).time().$idprocesso).".".retornaExtensaoArquivo($_FILES['userfile']['name']);
            $destino = $caminho.$pasta.$link;
            //envia arquivo para o servidor
            if(move_uploaded_file($_FILES['userfile']['tmp_name'],$destino)){
              //define o arquivo como 664
              chmod($destino,0664);
            }else{
              //não enviou o arquivo
              enviaMsg("erro","Não aprovação / aprovação não efetuada","O arquivo não pôde ser enviado para o servidor");
              echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
              exit();
            }

            //add infos do documento no Banco de Dados
            $Documento = new Documento();
            $Documento->setProcesso($idprocesso);
            $Documento->setUsuario($_SESSION["USUARIO"]["idusuario"]);
            $Documento->setDocumentoTipo(DOC_IDJUSTIFICATIVA);
            $Documento->setLink($link);
            $Documento->setObs($justificativa);
            $DocumentoDAO = new DocumentoDAO(); 
            $iddocumento = $DocumentoDAO->insert($Documento);

            //problema ao enviar documento
            if(!$iddocumento || $iddocumento===false || $iddocumento==false){
              enviaMsg("erro","Documento não inserido","O documento não pôde ser registrado no banco de dados");
              echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
              exit();
            //documento enviado com sucesso
            }else{
              //GRAVAR DADOS NO LOG
              //se inseriu o documento com sucesso => SALVAR NO HISTÓRICO
              $Historico = new Historico();
              $Historico->setAcao(LOG_ADD_DOC);
              $Historico->setProcesso($idprocesso);  
              $Historico->setDocumento($iddocumento);
              $Historico->setObs(NULL);
              $HistoricoDAO = new HistoricoDAO();
              $inseriuLog=$HistoricoDAO->insert($Historico);
            }

          }//fim else do if "extensãocorreta"
        }//fim if "arquivo justificativa"

        //salva não aprovação e a msg
          //carrega dados da última etapa do processo encontrado em ETAPA_PROCESSO
          $dadosEtapaAnterior=$EtapaDAO->getLastEtapaProcesso($Processo);
          //verifica se a última etapa de ETAPA_PROCESSO é a etapa atual do PROCESSO (consistência dos dados)
          if($dadosEtapaAnterior["idetapa"] != $infosprocesso["idetapa"]){
            //se não for, pega dados de todas etapas e compara com a etapa que está salva no processo
            $arrayEtapas = $EtapaDAO->getAll();
            foreach($arrayEtapas as $e){
              //quando encontrar o ID da etapa do processo, insere esta etapa como a última do etapa_processo
              if($e["idetapa"] == $infosprocesso["idetapa"]){
                $EtapaCorreta = new Etapa();
                $EtapaCorreta->setId($e["idetapa"]);
                $EtapaCorreta->setProcesso($idprocesso);
                $EtapaCorreta->setUsuario1($_SESSION["USUARIO"]["idusuario"]);
                $EtapaCorreta->setAprova($e["aprova"]);
                $EtapaCorreta->setAprovaMsg(NULL);
                //insere o ETAPA_PROCESSO
                $inseriuEtapaProcesso = $EtapaDAO->insertEtapaProcesso($EtapaCorreta);
                //sai do foreach
                break; 
              }
            }
          }

          //atualiza a etapa_processo com a última ação do usuário
          $dadosEtapaAnterior=$EtapaDAO->getLastEtapaProcesso($Processo);
          $Etapa->setId($dadosEtapaAnterior["idetapa_processo"]);
          $Etapa->setUsuario2($_SESSION["USUARIO"]["idusuario"]);
          $Etapa->setAprova($aprovou);
          $Etapa->setAprovaMsg($justificativa);
          $atualizaEtapaProcesso = $EtapaDAO->updateEtapaProcesso($Etapa);
          
          //redefine o ID da etapa, pois logo abaixo é preciso saber se ela possui e-mails p/ enviar
          $Etapa->setId($infosprocesso["idetapa"]);

        //enviar e-mails
          $emailsetapa = $EtapaDAO->getEmails($Etapa);
          //se houver e-mails a enviar
          if(sizeof($emailsetapa)>0){
            //var de controle
            $emails = array();
            //para cada email 
            for($i=0;$i<sizeof($emailsetapa);$i++){
              if($emailsetapa[$i]["numero"] == 1){
                $emails[$i]["mensagem"]=$infosetapa["msgemail1"];
              }else{
                $emails[$i]["mensagem"]=$infosetapa["msgemail2"];
              }
              if(isset($emailsetapa[$i]["idperfil"]) && !empty($emailsetapa[$i]["idperfil"])){
                $emails[$i]["idperfil"]=$emailsetapa[$i]["idperfil"];
              }else{
                $emails[$i]["idperfil"]=NULL;
              }
              if(isset($emailsetapa[$i]["idusuario"]) && !empty($emailsetapa[$i]["idusuario"])){
                $emails[$i]["idusuario"]=$emailsetapa[$i]["idusuario"];
              }else{
                $emails[$i]["idusuario"]=NULL;
              }
              $emails[$i]["tipoemail"] = $emailsetapa[$i]["tipoemail"];
            }
            //define variaveis para o ajax_mail
            $tipoajaxmail="index_doc";
            $nomeprocesso='Processo de '.$infosprocesso["nometipo"].' nº '.$infosprocesso["numero"];
            $linkprocesso=APP_URL.'/control/index_doc.php?p='.$idprocesso;
            $processousuarioid=$infosprocesso["idusuario"];
            $PerfilDAO = new PerfilDAO();
            $dadosperfil=$PerfilDAO->getOne($_SESSION["USUARIO"]["idperfil"]);
            $nomeperfilusuario=$dadosperfil["nome"];
            //envia e-mails     
            require_once("ajax_mail.php");
          }

        //Atualizar historico da etapa atual em etapa_processo
          //retorna dados da etapa atual
          $dadosEtapaAtual=$EtapaDAO->getLastEtapaProcesso($Processo);
          //cria array com todas etapas do fluxo
          $arrayEtapas = $EtapaDAO->getAll();
          //Objeto ETAPA para instanciar infos da proxima etapa
          $ProximaEtapa = new Etapa();
          //Recebe informações da próxima etapa
          $ProximaEtapa = proximaEtapaProcesso2($arrayEtapas,$dadosEtapaAtual,$Processo);

          //se o ID da ProximaEtapa >= 0 é possível trocar de etapa, então insere a nova etapa
          if($ProximaEtapa->getId() >= 0){
            //insere nova etapa no histórico do processo
            $inseriuEtapaProcesso = $EtapaDAO->insertEtapaProcesso($ProximaEtapa);
            //atualiza processo
            $atualizouEtapa = $EtapaDAO->updateEtapa($ProximaEtapa);
            //atualiza prazo da etapa do processo
            //calcula o prazo de acordo com o valor em dias que vier do getPrazo
            if($ProximaEtapa->getPrazo()>0){
              $dtprazo=date('Ymd', strtotime('+'.$ProximaEtapa->getPrazo().' days'));
            }else{
              $dtprazo=0;
            }             
            $Processo->setPrazo($dtprazo);
            $atualizouDtPrazo = $ProcessoDAO->updateDtPrazo($Processo);
          }
          
        //salva histórico
          //GRAVAR DADOS NO LOG
          //define observações:
          //se inseriu o documento com sucesso => SALVAR NO HISTÓRICO
          $Historico = new Historico();
          $Historico->setAcao(LOG_UPDATE_PRO);
          $Historico->setProcesso($idprocesso);
          $Historico->setDocumento(0);
          $Historico->setObs("Documento não aprovado - Etapa ".$infosetapa["ordem"]." ".$infosetapa["nome"].APP_LINE_BREAK."Justificativa: ".$justificativa);
          $HistoricoDAO = new HistoricoDAO();
          $inseriuLog=$HistoricoDAO->insert($Historico);
          if(!$inseriuLog){
            enviaMsg("erro","O processo foi atualizado com erros","O histórico de não aprovação não pôde ser salvo");
            echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
            exit();
          }

        //dá msg de sucesso
        enviaMsg("sucesso","Não aprovação salva com sucesso");
        echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=".$idprocesso."\">";
        exit();

      }else{

        //se escolheu ñ aceitar uma data mas não definiu uma justificativa, avisa o usuário
        enviaMsg("erro","Processo não atualizado","Toda não aprovação de documento exige justificativa");
        echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=".$idprocesso."\">";
        exit();

      }


    }

  }


	if(!empty($infosprocesso["numero"])){
    
    echo'
    <div class="div_sup_documento">
        		<p>
              <center>
                <span style="font-size:22px;font-weight:bold;">Processo de '.$infosprocesso["nometipo"].' nº '.$infosprocesso["numero"].'</span><br>';
                

    echo'     </center>
            </p>
        </div>';
	
	?>
<div class="view_ar_doc"></div>
<div class="edit_ar_doc"></div>
<div class="del_ar_doc"></div>

<div id="conteudo_borda">
  <div class="container">

    <?php
    //verifica a necessidade de tomar alguma ação direto na capa do processo
    //se não envia nenhum documento nesta etapa porém alguma ação é aguardada:
    if($usuarioEfetuaAcoes && !$bloquearProcesso && $infosetapa["msgcapa"] && (!$numdocs || $numdocs<1) && ($infosetapa["aprova"]==ETAPA_AGUARDANDO_APROVACAO || $infosetapa["escolhedata"]==ETAPA_ESCOLHE_DATA || $infosetapa["etapatipo"]==ETAPA_ESCOLHE_TIPO || $infosetapa["etapatipo"]==ETAPA_ESCOLHE_RECURSO)){
      echo "  <div class=\"well bg-msg\">
                <center><strong>".textoMaiusculo($_SESSION["USUARIO"]["nome"]).", ABAIXO SUA AÇÃO É NECESSÁRIA:</strong></center>
                <hr>
                <p>".$infosetapa["msgcapa"]."</p>";

      //se for etapa que decide se o processo será C/ ou S/ eleições
      if($infosetapa["etapatipo"]==ETAPA_ESCOLHE_TIPO){

              echo "<form id=\"index_doc_aprova\" enctype=\"multipart/form-data\" name=\"index_doc_aprova\" action=\"index_doc.php\" method=\"post\" class=\"form-horizontal\">
                      <input type=\"hidden\" name=\"p\" id=\"p\" value=\"".$idprocesso."\">";

                          echo "<div class=\"form-group\">
                                  <label class=\"col-sm-12 control-label\">Escolha se houve ou não candidatos ao pleito</label>
                                  <div class=\"col-lg-6\">
                                    <select id=\"candidatos\" name=\"candidatos\" class=\"form-control\" onchange=\"exibeDiv('candidatos',this.value);\">
                                      <option value=\"-1\" selected=\"selected\">Selecione</option>
                                      <option value=\"sem\">Não houve candidatos ao pleito</option>
                                      <option value=\"com\">Houve candidatos ao pleito</option>
                                    </select>
                                    <span id=\"helpBlock\" class=\"help-block\">".APP_MSG_REQUIRED."</span>
                                  </div>
                                </div> ";

                          //CAMPO PARA ANEXAR LISTA DE CANDIDATOS
                          echo "  <div class=\"form-group campoviaselect candidatos candidatos_com\">
                                    <label class=\"col-sm-10 control-label\">Lista de Pessoas Inscritas</label>
                                      <div class=\"col-lg-10\">
                                          <input name=\"userfile\" id=\"userfile\" type=\"file\" value=\"\" />
                                          <span class=\"help-block\">O arquivo precisa ser do tipo <strong>DOC</strong>, <strong>DOCX</strong>, <strong>PDF</strong>, <strong>XLS</strong>, <strong>XLSX</strong> ou <strong>ODT</strong></span>
                                      </div>
                                  </div>";
                          

                          echo "                        
                        <div class=\"form-group\">
                          <div class=\"col-lg-10\" id=\"processo_".$idprocesso."\">
                            <button type=\"reset\" id=\"cancelar\" class=\"btn btn-default index_doc.php?p=".$idprocesso."\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cancelar&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</button>
                            <button type=\"submit\" class=\"btn btn-primary enviando_formulario\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Enviar resposta&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</button>
                          </div>
                        </div>
                      </form>";

      //se cair aqui é etapa que decide se o processo será C/ ou S/ eleições
      }elseif($infosetapa["etapatipo"]==ETAPA_ESCOLHE_RECURSO){

              echo "<form id=\"index_doc_aprova\" enctype=\"multipart/form-data\" name=\"index_doc_aprova\" action=\"index_doc.php\" method=\"post\" class=\"form-horizontal\">
                      <input type=\"hidden\" name=\"p\" id=\"p\" value=\"".$idprocesso."\">";

                          echo "<div class=\"form-group\">
                                  <label class=\"col-sm-12 control-label\">Escolha se houve ou não recursos/questionamentos às eleições realizadas</label>
                                  <div class=\"col-lg-6\">
                                    <select id=\"recursos\" name=\"recursos\" class=\"form-control\" onchange=\"exibeDiv('recursos',this.value);\">
                                      <option value=\"-1\" selected=\"selected\">Selecione</option>
                                      <option value=\"com\">Houve recursos/questionamentos e respostas da Comissão Eleitoral</option>
                                      <option value=\"sem\">Não houve recursos/questionamentos</option>                                      
                                    </select>
                                    <span id=\"helpBlock\" class=\"help-block\">".APP_MSG_REQUIRED."</span>
                                  </div>
                                </div> ";

                          //CAMPO PARA ANEXAR RECURSOS
                          echo "  <div class=\"form-group campoviaselect recursos recursos_com\">
                                    <label class=\"col-sm-10 control-label\">Registro de recursos e respostas da Comissão Eleitoral</label>
                                      <div class=\"col-lg-10\">
                                          <input name=\"userfile\" id=\"userfile\" type=\"file\" value=\"\" />
                                          <span class=\"help-block\">O arquivo precisa ser do tipo <strong>DOC</strong>, <strong>DOCX</strong>, <strong>PDF</strong>, <strong>XLS</strong>, <strong>XLSX</strong> ou <strong>ODT</strong></span>
                                      </div>
                                  </div>";
                          

                          echo "                        
                        <div class=\"form-group\">
                          <div class=\"col-lg-10\" id=\"processo_".$idprocesso."\">
                            <button type=\"reset\" id=\"cancelar\" class=\"btn btn-default index_doc.php?p=".$idprocesso."\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cancelar&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</button>
                            <button type=\"submit\" class=\"btn btn-primary enviando_formulario\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Enviar resposta&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</button>
                          </div>
                        </div>
                      </form>";
          


      //se cair aqui é apenas para clicar em Aprovar ou Reprovar
      }else{
        /*<form enctype=\"multipart/form-data\" name=\"index_doc_aprova\" action=\"index_doc.php\" method=\"post\" class=\"form-horizontal\" onSubmit=\"return validaForm('index_doc_aprova','aprova_doc');\" >*/        
        echo "<form id=\"index_doc_aprova\" enctype=\"multipart/form-data\" name=\"index_doc_aprova\" action=\"index_doc.php\" method=\"post\" class=\"form-horizontal\">
                      <input type=\"hidden\" name=\"p\" id=\"p\" value=\"".$idprocesso."\">";

                          echo "<div class=\"form-group\">
                                  <label class=\"col-sm-12 control-label\">Escolha se aprova ou não aprova o documento enviado</label>
                                  <div class=\"col-lg-6\">
                                    <select id=\"aprova_doc\" name=\"aprova_doc\" class=\"form-control\" onchange=\"exibeDiv('aprovacao',this.value);\">
                                      <option value=\"-1\" selected=\"selected\">Selecione</option>
                                      <option value=\"".ETAPA_NAO_APROVADA."\">Não, eu não aprovo o documento enviado</option>
                                      <option value=\"".ETAPA_APROVADA."\">Sim, eu aprovo o documento enviado</option>
                                    </select>
                                    <span id=\"helpBlock\" class=\"help-block\">".APP_MSG_REQUIRED."</span>
                                  </div>
                                </div> ";


                          echo "<div class=\"form-group campoviaselect aprovacao aprovacao_".ETAPA_NAO_APROVADA."\">
                                <label for=\"justificativa\" class=\"col-sm-10 control-label\">Justificativa</label>
                                <div class=\"col-sm-5\">
                                  <textarea class=\"form-control\" name=\"justificativa\" id=\"justificativa\" rows=\"5\"></textarea>
                                  <span id=\"helpBlock\" class=\"help-block\">".APP_MSG_REQUIRED."</span>
                                </div>
                                </div>";

                          //CAMPO PARA ANEXAR ARQUIVO DE NÃO APROVAÇÃO
                          echo "  <div class=\"form-group campoviaselect aprovacao aprovacao_".ETAPA_NAO_APROVADA."\">
                                    <label class=\"col-sm-10 control-label\">Arquivo (opcional)</label>
                                      <div class=\"col-lg-10\">
                                          <input name=\"userfile\" id=\"userfile\" type=\"file\" value=\"\" />
                                          <span class=\"help-block\">O arquivo precisa ser do tipo <strong>DOC</strong>, <strong>DOCX</strong>, <strong>PDF</strong>, <strong>XLS</strong>, <strong>XLSX</strong> ou <strong>ODT</strong></span>
                                      </div>
                                  </div>";
                          

                          echo "                        
                        <div class=\"form-group\">
                          <div class=\"col-lg-10\" id=\"processo_".$idprocesso."\">
                            <button type=\"reset\" id=\"cancelar\" class=\"btn btn-default index_doc.php?p=".$idprocesso."\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cancelar&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</button>
                            <button type=\"submit\" class=\"btn btn-primary enviando_formulario\">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Enviar resposta&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</button>
                          </div>
                        </div>
                      </form>";
      }
      echo "  </div> ";
    } 
    ?>


    <!-- STATUS da etapa atual / perfil que faz ações na etapa -->
    <div class="panel panel-primary">
      <div class="panel-heading">
        <h3 class="panel-title"><span class="glyphicon glyphicon-play" aria-hidden="true"></span> Status do processo</h3>
      </div>
      <div class="panel-body">
        <?php 
        if(sizeof($etapaperfisnomesarray)>0 && $infosprocesso["idetapa"]!=ID_LAST_ETAPA){
          echo "Em análise pela <strong><span style='color:red;'>".textoMaiusculo(exibeTextoComVirgulaOuE($etapaperfisnomesarray,"nomeperfil"))."</span></strong>";
        }else{
          echo "<button type=\"button\" class=\"btn btn-success btn-sm disabled\">&nbsp;&nbsp;&nbsp;&nbsp;ENCERRADO&nbsp;&nbsp;&nbsp;&nbsp;</button>";
        }
        ?>        
      </div>
    </div>
    <!-- FIM status do processo -->

    <!-- Responsaveis pelo processo -->
    <?php
    //essa "box" só é visualizada se houver responsável atribuído
    if($responsaveis!==false && sizeof($responsaveis)>0){
    ?>
    <div class="panel panel-primary">
      <div class="panel-heading">
        <h3 class="panel-title"><span class="glyphicon glyphicon-user" aria-hidden="true"></span> Membro da CEC (Coren-SC) responsável pelo processo</h3>
      </div>
      <div class="panel-body">
        <?php 
        echo exibeTextoComVirgulaOuE($responsaveis,"nomeusuario");
        ?>        
      </div>
    </div>
    <?php
    }
    ?>
    <!-- FIM Responsaveis pelo processo -->        


    <!-- data posse -->
    <?php
    if($infosprocesso["dtescolhida"]>0){
    ?>    
    <div class="panel panel-success">
      <div class="panel-heading">
        <h3 class="panel-title"><span class="glyphicon glyphicon-calendar" aria-hidden="true"></span> Posse </h3>
      </div>
      <div class="panel-body">
        <?php echo '<strong>Data</strong>: '.exibeDataTimestamp($infosprocesso["dtescolhida"]); ?>
      </div>
    </div>
    <?php
    }
    ?>
    <!-- FIM data posse -->


    <!-- data lembrete renovação -->
    <?php if(verificaFuncaoUsuario(FUNCAO_PROCESSO_EDIT) && $infosprocesso["idetapa"]==ID_LAST_ETAPA){ ?>
    <div class="panel panel-primary">
      <div class="panel-heading">
        <h3 class="panel-title"><span class="glyphicon glyphicon-bell" aria-hidden="true"></span> Lembrete de Renovação</h3>
      </div>
      <div class="panel-body">
        <?php 
        if($infosprocesso["dtfim"]>0){
          echo "<strong>Data programada:</strong> ".exibeData($infosprocesso["dtfim"])."<br>";
          if($infosprocesso["dtaviso"]>0){
            echo "<strong>Status:</strong> e-mail enviado em ".exibeDataTimestamp($infosprocesso["dtaviso"]);
          }else{
            echo "<strong>Status:</strong> e-mail não enviado";
          }
          
        }else{
          echo "Nenhuma data programada.";
        }
        
        ?>        
      </div>
    </div>
    <?php } ?>
    <!-- FIM data lembrete renovação -->


    <!-- GRAFICO etapa atual -->

    <div class="panel panel-primary">
      <div class="panel-heading">
        <h3 class="panel-title"><span class="glyphicon glyphicon-flag" aria-hidden="true"></span> Etapa atual e sua posição no fluxograma</h3>
      </div>
      <div class="panel-body">

 <!--INÍCIO QFLUXO -->  
<?php 

  //trata o array com a sequencia das etapas exibindo:
  //Etapas principais & etapa atual do processo
  $arrayqfluxo = array();
  $indiceqfluxo=0;
  $cont_ordem=1;
  foreach ($arrayEtapas as $e) {
    //exibe apenas as etapas pertinentes aquele processo
    if(
      $e["idetapa"]==$infosetapa["idetapa"]
      ||      
      (
        //se o processo for de instituição militar
        ($Processo->getMilitar()==PROCESSO_MILITAR 
          && ($e["modo"]==PROCESSOETAPA_SEMELEICOES)
        )
        ||
        //se o processo for de instituição com eleições
        ($Processo->getModo()==PROCESSOETAPA_COMELEICOES
          && ($e["modo"]==PROCESSOETAPA_COMELEICOES || $e["modo"]==ETAPA_NAOMILITAR)
        )
        ||
        //se o processo for de instituição sem eleições
        ($Processo->getModo()==PROCESSOETAPA_SEMELEICOES 
          && (($Processo->getMilitar()!=PROCESSO_MILITAR && $e["modo"]==ETAPA_NAOMILITAR) || $e["modo"]==PROCESSOETAPA_SEMELEICOES)
        )
        ||
        //se o processo for modo normal
        ($Processo->getModo()==PROCESSOETAPA_NORMAL
          && $Processo->getMilitar()!=PROCESSO_MILITAR && $e["modo"]==ETAPA_NAOMILITAR
        )
        ||
        $e["modo"]==PROCESSOETAPA_NORMAL
      )
      &&
      (
      $e["fluxo"]==ETAPA_PRINCIPAL
      )
    ){
      //armazena o ID & Nome da etapa
      $arrayqfluxo[$indiceqfluxo]["idetapa"]=$e["idetapa"];
      $arrayqfluxo[$indiceqfluxo]["nome"]=$e["nome"];
      $arrayqfluxo[$indiceqfluxo]["descricao"]=$e["descricao"];
      $arrayqfluxo[$indiceqfluxo]["ordem"]=$e["ordem"];
      //se a etapa atual bloquear o processo, interrompe o fluxo também
      if($e["idetapa"]==$infosetapa["idetapa"] && $bloquearProcesso){
        break;
      }
      $indiceqfluxo++;
    }    
  }

  //se houver fluxo para exibir:
  if(sizeof($arrayqfluxo)>0){

    //Q_FLUXO
    echo'<div class="q_fluxo">';

      //navegação:
      echo'<div class="q_fluxo_nav">';
      for($i=0;$i<sizeof($arrayqfluxo);$i++){
        echo '<div class="botao botao'.($i+1);
        //se for a etapa atual do processo marca ela como ativa
        if($arrayqfluxo[$i]["idetapa"]==$infosetapa["idetapa"]){
          echo ' bEtapaAtual ';
        }
        echo '" id="botao'.($i+1).'" title="';
        //se for a etapa atual do processo
        if($arrayqfluxo[$i]["idetapa"]==$infosetapa["idetapa"]){
          echo '(ETAPA ATUAL) ';
        }
        echo $arrayqfluxo[$i]["nome"].'"><center><strong><span class="glyphicon ';
        //se for a última etapa =processo conclúido OU apenas o ícone da última etapa
        if($arrayqfluxo[$i]["idetapa"]==ID_LAST_ETAPA){
          echo 'glyphicon-thumbs-up';
        //se for a etapa atual do processo
        }elseif($arrayqfluxo[$i]["idetapa"]==$infosetapa["idetapa"]){
          //echo 'glyphicon-map-marker';          
          echo 'glyphicon-play-circle';
        //se for uma etapa que já foi realizada
        }elseif($arrayqfluxo[$i]["ordem"] < $infosetapa["ordem"]){
          echo 'glyphicon-ok-circle';
          //echo 'glyphicon-ok';
        //se for uma etapa à realizar
        }else{
          echo 'glyphicon-play-circle';
          //echo 'glyphicon-time';
          //echo 'glyphicon-circle-arrow-right';
        }
        echo '" aria-hidden="true" style="line-height:2 !important;"></span> </strong></center></div>';
      }
      //fim-navegação
      echo'</div>';

      //setas:
      for($i=0;$i<sizeof($arrayqfluxo);$i++){
        echo '<div class="seta"';
        //se for a etapa atual do processo manda exibir ela
        if($arrayqfluxo[$i]["idetapa"]==$infosetapa["idetapa"]){
          echo ' style="display:block;" ';
        }
        echo ' id="seta_botao'.($i+1).'"></div>';
      }
      //fim-setas 

      //conteúdo:
      echo '<div class="q_fluxo_conteudo">';
      for($i=0;$i<sizeof($arrayqfluxo);$i++){
        echo '<div class="texto_botao" id="texto_botao'.($i+1).'" ';
        //se for a etapa atual do processo manda exibir o texto dela
        if($arrayqfluxo[$i]["idetapa"]==$infosetapa["idetapa"]){
          echo ' style="display:block;" ';
        }
        echo '>
                <p><strong>'.$arrayqfluxo[$i]["nome"].'</strong></p>
                <p>'.$arrayqfluxo[$i]["descricao"].'</p>
              </div>';
      }
      //fim-conteúdo
      echo '</div>';      
      

    //FIM Q_FLUXO 
    echo'</div>';

  }else{

    echo "<p>Sem etapas cadastradas</p>";

  }

?>

    </div>
    </div>
    <!-- FIM GRAFICO etapa atual -->


    <!-- Prazo da etapa atual -->
    <?php 
    //só exibe essa DIV se não for a última etapa
    if($infosprocesso["idetapa"]!=ID_LAST_ETAPA){
    ?>
      <div class="panel panel-primary">
        <div class="panel-heading">
          <h3 class="panel-title"><span class="glyphicon glyphicon-time" aria-hidden="true"></span> Prazo para conclusão da etapa atual</h3>
        </div>
        <div class="panel-body">
          <?php 
          if($infosprocesso["dtprazo"]>0){

            echo exibeData($infosprocesso["dtprazo"]);
            $data1 = date("Ymd");//dtatual
            $data2 = $infosprocesso["dtprazo"];//dtlimite
            // converte as datas para o formato timestamp
            $d1 = strtotime($data1); 
            $d2 = strtotime($data2); 
            // verifica a diferença em segundos entre as duas datas e divide pelo número de segundos que um dia possui
            $dataFinal = ceil(($d2 - $d1) /86400);
            $txtdtprazo="";
            // caso a data 2 seja menor que a data 1
            if($dataFinal < 0){
              $dataFinal = $dataFinal * -1;
              $txtdtprazo=" <span style='color:red'>($dataFinal dias de atraso)</span>";
            }elseif($dataFinal==0){
              $txtdtprazo=" <span style='color:red'>(hoje é o último dia do prazo)</span>";
            }elseif($dataFinal==1){
              $txtdtprazo=" (falta $dataFinal dia para o fim do prazo)";
            }else{
              $txtdtprazo=" (faltam $dataFinal dias para o fim do prazo)";
            }
            echo $txtdtprazo;

          }else{
            echo "Indeterminado";
          }
          ?>        
        </div>
      </div>
    <?php
    } //fim IF de exibição (só quando não for a última etapa)
    ?>
    <!-- FIM prazo de etapa -->

<?php
    //se houver msg capa, exibe as orientações da etapa
    if(!empty($infosetapa["msgcapa"])){
    ?>
    <div class="panel panel-primary">
      <div class="panel-heading">
        <h3 class="panel-title"><span class="glyphicon glyphicon-list-alt" aria-hidden="true"></span> Orientações da etapa atual</h3>
      </div>
      <div class="panel-body">

        <?php echo $infosprocesso["msgcapa"]; ?>
        
        <!-- <center><img src="../etapa.png"></center> -->
      </div>
    </div>
    <?php } ?>
    <!-- FIM ORIENTAÇÕES DA ETAPA ATUAL -->


    <!-- dados instituicionais -->
    <div class="panel panel-primary">
      <div class="panel-heading">
        <h3 class="panel-title"><span class="glyphicon glyphicon-briefcase" aria-hidden="true"></span> Dados Institucionais</h3>
      </div>
      <div class="panel-body">
          <p>
            <?php
            
            echo '<strong>Nome da Instituição</strong>: '.$infosprocesso["nome_instituicao"];
            echo '<br><strong>Cidade</strong>: '.$infosprocesso["nomecidade"];
            echo '<br><strong>Subseção</strong>: '.$infosprocesso["nomesubsecao"].' - '.$infosprocesso["nomecidadesubsecao"];
            echo '<br><strong>Nome do Responsável</strong>: '.$infosprocesso["nomeresponsavel"];
            // echo '<br><strong>Presidente da CEE</strong>: '.$infosprocesso["nomepresidentecee"];
            // echo '<br><strong>Secretário(a) da CEE</strong>: '.$infosprocesso["nomesecretariocee"];   
            
              // Verifica se os valores de presidente e secretário são nulos e exibe o nome padrão se for o caso
              $nomePresidente = isset($infosprocesso["nomepresidentecee"]) && $infosprocesso["nomepresidentecee"] ? $infosprocesso["nomepresidentecee"] : " ";
              $nomeSecretario = isset($infosprocesso["nomesecretariocee"]) && $infosprocesso["nomesecretariocee"] ? $infosprocesso["nomesecretariocee"] : " ";
              

              //Se o valor do campo presidente da cee for nulo não é listado na página, caso contrário será , atualizado 2025 thiago
              if(!isset($infosprocesso["nomepresidentecee"])){
                
                }
                else{
                  echo '<br><strong>Presidente da CEE</strong>: '.$nomePresidente;
                }
              //Se o valor do campo secretário da cee for nulo não é listado na página, caso contrário será , atualizado 2025 thiago

              if(!isset($infosprocesso["nomesecretariocee"])){
              }
              else{
                echo '<br><strong>Secretário(a) da CEE</strong>: '.$nomeSecretario;

              }

             


            echo '<br><strong>Login</strong>: ';
            //se o usuário tiver permissão para editar usuários permite ir para edição
            if(verificaFuncaoUsuario(FUNCAO_USUARIO_EDIT)){
              echo '<a title="Clique aqui para editar o usuário" target="_blank" href="edit_user.php?id='.$infosprocesso["idusuario"].'">'.$infosprocesso["login"].' (clique para atualizar dados da instituição)</a>';
            //caso não tenha permissão apenas exibe o login do usuário
            }else{
              echo $infosprocesso["login"];
            }
            echo '<br><strong>Celular</strong>: '.exibeTelefone($infosprocesso["celular"]);
            echo '<br><strong>Telefone</strong>: '.exibeTelefone($infosprocesso["telefone"]);
            
            if(!empty($infosprocesso["email1"])){
              echo '<br><strong>Email Principal</strong>: <a href="mailto:'.$infosprocesso["email1"].'">'.$infosprocesso["email1"].'</a>';
            }
            if(!empty($infosprocesso["email2"])){
              echo '<br><strong>Email Secundário</strong>: <a href="mailto:'.$infosprocesso["email2"].'">'.$infosprocesso["email2"].'</a>';
            }

            if($infosprocesso["dtexpiracao"]>0){
              echo '<br><strong>Data de expiração do acesso</strong>: '.exibeData($infosprocesso["dtexpiracao"]);
            }

            ?>
          </p>
      </div>
    </div>
    <!-- FIM dados instituicionais -->

    <!-- área para envio de e-mail -->
    <?php
    //se for instituição, precisa ter um responsável definido, do contrário permite enviar email
    if((isInstituicao() && $responsaveis!==false && sizeof($responsaveis)>0) || !isInstituicao()){
    ?>
    <div class="dados_pad noprint" id="<?php echo $idprocesso; ?>">
      <p><center>
        <input type="hidden" id="idusuario" value="<?php echo $infosprocesso["idusuario"]; ?>">
        <button id="envia_email_processo" class="btn btn-primary" aria-label="Left Align" type="button" title="">Avisar por e-mail um usuário sobre esse processo</button>
        <div id="dados_envio_email" style="display:none;"></div>
      </center><br></p>
    </div>
    <?php
    }
    ?>
    <!-- FIM enviar e-mail -->


    <?php

    //exibição dos documentos
    if(sizeof($documentos)>0){

    //define o cabeçalho
    echo '<table class="table table-condensed table-responsive table-hover">
          <thead>
            <tr>
            <th><a class="reordenar_doc" href="'.$idprocesso.'|dtenvio|">Data de Envio</a></th>
            <th><a class="reordenar_doc" href="'.$idprocesso.'|nomedocumento|">Documento</a></th>
            <th><a class="reordenar_doc" href="'.$idprocesso.'|nomeusuario|">Autor</a></th>                  
            <th class="noprint">Ações</th>
            </tr>
          </thead>
          <tbody>';

            //varre o array de documentos
            foreach($documentos as $doc){              

              //exibe informações do documento
              echo '<tr id="'.$idprocesso.'|'.$doc["iddocumento"].'">
                                <td scope="row">'.exibeDataTimestamp($doc["dtcriacao"]).'</td><td>';

              //se o documento tiver observações, exibe elas e o ícone de mensagem
              if(!empty($doc["obs"])){
                //echo '<span class="glyphicon glyphicon-comment brilhante" data-toggle="tooltip" title="Observações do documento:&nbsp;&nbsp;'.$doc["obs"].'"></span> ';
                echo '<span class="glyphicon glyphicon-comment brilhante" data-toggle="modal" data-target="#modalForm" itemprop="'.$doc["obs"].'" title="Clique no balão de comentário para visualizar as observações deste documento."></span> ';
                
              }


              echo              exibeTexto($doc["nomedocumento"]);
              if(!empty($doc["dtatualizacao"]) && $doc["dtatualizacao"]>0 && !empty($doc["idusuarioatualizacao"]) && $doc["idusuarioatualizacao"]>0){
                echo ' <span style="color:red">(documento atualizado em <em>'.exibeDataTimestamp($doc["dtatualizacao"]).'</em> por <em>'.$doc["nomeusuarioatualizacao"].'</em>)</span>';
              }
              echo            '</td>
                                <td>'.exibeTexto($doc["nomeusuario"]).'</td>
                                <td class="noprint">
                                <nobr>
                                <button class="btn btn-primary index_doc_view" aria-label="Left Align" type="button"  title="Visualizar documento"> <span class="glyphicon glyphicon-zoom-in" aria-hidden="true"></span> </button> ';
                                /*
                                NENHUM DOCUMENTO É EDITÁVEL, POR ENQUANTO. Acho que deve voltar a ter essa opção, permitindo apenas trocar o arquivo e os observações enquanto ninguém tiver visualizado o arquivo!
                                */
                                //se puder editar o documento
                                if(!$bloquearProcesso && verificaFuncaoUsuario(FUNCAO_DOCUMENTO_EDIT)){
                                  echo '&nbsp;<button class="btn btn-success index_doc_edit" aria-label="Left Align" type="button" title="Editar documento"> <span class="glyphicon glyphicon-pencil" aria-hidden="true"></span> </button> ';
                                }
                                //se puder remover o documento E foi enviado por ele ou ele é admin/comissao/presidente:
                                if(!$bloquearProcesso && verificaFuncaoUsuario(FUNCAO_DOCUMENTO_DEL)){
                                  echo '&nbsp;<button class="btn btn-warning index_doc_del" aria-label="Left Align" type="button" title="Excluir documento"> <span class="glyphicon glyphicon-trash" aria-hidden="true"></span> </button> ';
                                };
              echo '            </nobr></td></tr>';


            //fim array de documentos
            }

          //fim exibição dos documentos
          echo '</tbody>
                          </table>
                    </div></div></div>';

    //se não tiver nenhum documento enviado até o momento
    }else{  ?>
      <div class="dados_pad">
        <p><center>Nenhum documento foi enviado para este processo até o momento</center><br></p>
      </div>
    <?php 
    }
    ?>
    
	</div>

  </div><!-- FIM div class="container" -->
</div><!-- FIM div id="conteudo_borda" -->

<!-- Modal -->
<div class="modal fade" id="modalForm" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">

            <!-- Modal Header -->
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                    <span class="sr-only">Fechar</span>
                </button>
                <h4 class="modal-title" id="myModalLabel">Observações do documento:</h4>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body">
                <p class="statusMsg"></p>
                <!--<form role="form">-->
                    <div class="form-group">
                        <p id="myModalContent">&nbsp;</p>
                    </div>                   
                <!--</form>-->
            </div>
            
            <!-- Modal Footer -->
            <div class="modal-footer">
                <center><button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button></center>
            </div>
        </div>
    

<?php
    include_once("../menu_rodape.php");

    //INSERIDO NO RODAPÉ DE PÁGINAS COM MAIOR ACESSO (INDEX/INDEX_PRO/INDEX_DOC) - FUNÇÕES AUTOMÁTICAS DE CONFIG DO SISTEMA
    require_once("@config.php");

	}else{
      enviaMsg("erro","Acesso negado","Sem permissão de acesso, link quebrado ou dados inválidos");
      echo "<meta http-equiv=\"refresh\" content=\"0; url=index_pro.php\">";
      exit();
  }
}else{
  enviaMsg("erro","Acesso negado","Sem permissão de acesso, link quebrado ou os dados são inválidos");
  echo "<meta http-equiv=\"refresh\" content=\"0; url=index_pro.php\">";
  exit();
}
}else{
  enviaMsg("erro","Acesso negado","Sem permissão de acesso, link quebrado ou foram dados inválidos");
  echo "<meta http-equiv=\"refresh\" content=\"0; url=index_pro.php\">";
  exit();
}
?>
