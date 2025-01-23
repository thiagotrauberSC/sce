<?php



require_once("../config.php");
require_once("../bin/errors.php");
require_once("../bin/functions.php");
require_once("../bin/js-css.php");
require_once("../login_verifica.php");

if(isset($_REQUEST["p"]) && !empty($_REQUEST["p"])){
	$idprocesso=validaInteiro($_REQUEST["p"],PROCESSO_ID_SIZE);	
}else{
	$idprocesso=false;
}

if( $idprocesso && verificaFuncaoUsuario(FUNCAO_PROCESSO_EDIT)!==false && verificaProcessoUsuario($idprocesso)!==false ){

//conecta no banco e instacia uma conexão com o Registry
require_once("../conexao.php");
require_once("../model/Registry.php");
// Armazenar essa instância (conexão) no Registry - conecta uma só vez
$registry = Registry::getInstance();
$registry->set('Connection', $myBD);
//carrega DAO
require_once('../dao/ProcessoDAO.php');
require_once('../dao/PerfilDAO.php');
require_once("../dao/UsuarioDAO.php");
require_once("../dao/HistoricoDAO.php");
require_once("../dao/DocumentoDAO.php");
require_once("../dao/EtapaDAO.php");
require_once("../dao/ResponsavelDAO.php");
//carrega Model
require_once("../model/Processo.php");
require_once("../model/Historico.php");
require_once("../model/Documento.php");
require_once("../model/Etapa.php");
require_once("../model/Responsavel.php");

//Recupera informações do processo
// Instanciar o processo
$Processo = new Processo();
$Processo->setId($idprocesso);
// Instanciar o DAO e retornar infos da base
$ProcessoDAO = new ProcessoDAO();
$infosprocesso = $ProcessoDAO->getInfosCapa($Processo);
// Verifica se o método de requisição é POST

// Supondo que você já tenha incluído o código para autoload ou requerido a classe Processo e ProcessoDAO
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     // Recupera os dados do formulário
//     $idprocesso = isset($_POST['id']) ? $_POST['id'] : null;
//     $nomepresidentecee = isset($_POST['nomepresidentecee']) ? trim($_POST['nomepresidentecee']) : null;
//     $nomesecretariocee = isset($_POST['nomesecretariocee']) ? trim($_POST['nomesecretariocee']) : null;

//     // Verifica se os valores de presidente e secretário foram informados
//     if ($nomepresidentecee !== null && $nomesecretariocee !== null) {
//         // Instancia o objeto Processo
//         $Processo = new Processo();
//         $Processo->setId($idprocesso);
//         $Processo->setNomePresidenteCEE($nomepresidentecee);
//         $Processo->setNomeSecretarioCEE($nomesecretariocee);

//         // Instancia o DAO e chama o método para atualizar o processo
//         $ProcessoDAO = new ProcessoDAO();
//         if ($ProcessoDAO->update($Processo)) {
//             // Se a atualização for bem-sucedida, exibe uma mensagem de sucesso
//             echo "Processo atualizado com sucesso!";
//         } else {
//             // Caso contrário, exibe uma mensagem de erro
//             echo "Erro ao atualizar o processo.";
//         }
//     } else {
//         echo "Por favor, preencha todos os campos.";
//     }
// }




// Resto do código do formulário
// Instanciar DAO responsáveis e retornar infos
$ResponsavelDAO = new ResponsavelDAO();
$responsaveis = $ResponsavelDAO->getAllFrom($idprocesso);
if($infosprocesso["bloquear"]==ETAPA_BLOQUEIA_PROCESSO && !isAdmin()){
	enviaMsg("erro","Erro","Este processo não pode ser modificado");
	echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
	exit();
}
if(!empty($infosprocesso["dtescolhida"])){
	//se já houver uma data escolhida, seta como 1 o valor da variavel posse
	$posse = 1;
}else{
	//sem data definida
	$posse = 2;
}

//pega infos da Etapa atual
$EtapaDAO = new EtapaDAO();
$infosetapa = $EtapaDAO->getOne($infosprocesso["idetapa"]);

//se tiver enviado o formulário (IF Nº1)
if( !empty($_POST) && isset($_POST) ){
	
	//verificação dos dados obrigatórios (se formato e tamanho conferem)
	$processotipo=		validaInteiro($_POST["processotipo"],	PROCESSOTIPO_ID_SIZE);
	$etapa=				validaInteiro($_POST["etapa"],			ETAPA_ID_SIZE);
	$usuario=			validaInteiro($_POST["instituicao"],	USUARIO_ID_SIZE);
	$posse=				validaInteiro($_POST["posse"],	1);
	$militar=			validaInteiro($_POST["militar"],		PROCESSO_MILITAR_SIZE);
	$modo=				validaInteiro($_POST["modo"],			ETAPA_MODO_SIZE);
	

	// Validação obrigatória dos campos nomepresidentecee e nomesecretariocee
    $nomepresidentecee = isset($_POST["nomepresidentecee"]) ? trim($_POST["nomepresidentecee"]) : '';
    $nomesecretariocee = isset($_POST["nomesecretariocee"]) ? trim($_POST["nomesecretariocee"]) : '';
	
	//se os dados obrigatórios passarem na validação: (IF Nº2)
	if( $processotipo!==false && $etapa!==false && $usuario!==false && $posse!==false && $posse!==false && $modo!==false){

		$dtescolhida=false;
		$dtprazo=sqlTrataString($_POST["dtprazo"]);

		//trata dados referentes ao lembrete de renovação
		//02/12/2020 == 20201202 + 2(//)
		if(strlen($_POST["dtfim"])==(USUARIO_DTEXPIRACAO_SIZE+2)){
			$dtfim=transformaDataBanco($_POST["dtfim"]);
		}else{
			$dtfim=NULL;
		}
		//02/12/2020 11:00 == 2020/12/02 11:12:00 - 3(:00)
		if(strlen($_POST["dtaviso"])==(PROCESSO_DTPOSSE1_SIZE-3)){
			$dtaviso=transformaDataTimestampBanco($_POST["dtaviso"]);
		}else{
			$dtaviso=NULL;
		}
		
		//se for escolhido SIM para pergunta "possui data de posse" 
		if($posse == 1){
			if(isset($_POST["dtescolhida"]) && sizeof($_POST["dtescolhida"])>0){
				$dtescolhida=sqlTrataString($_POST["dtescolhida"]);
			}
			if(isset($_POST["trocaetapa"]) && $_POST["trocaetapa"]!==NULL){
				$trocaetapa=validaInteiro($_POST["trocaetapa"],1);
			}
		}

		//valida para entrar na área de segurança da SQL
		$processotipo=	sqlTrataInteiro($processotipo);
		$etapa=			sqlTrataInteiro($etapa);
		$usuario=		sqlTrataInteiro($usuario);
		$posse=			sqlTrataInteiro($posse);
		$militar=		sqlTrataInteiro($militar);
		$modo=			sqlTrataInteiro($modo);

		//se os dados obrigatórios passarem na validação de SQL: (IF Nº3)
		if( $processotipo!==false && $etapa!==false && $usuario!==false && $posse!==false && $militar!==false && $modo!==false){

			// Instanciar as infos do processo
			$Processo = new Processo();			
			// Instanciar o DAO para inserir na base
			$ProcessoDAO = new ProcessoDAO();

			$Processo->setId($idprocesso);
			$Processo->setUsuario($usuario);
			$Processo->setProcessoTipo($processotipo);
			$Processo->setEtapa($etapa);
			$Processo->setDtAtualizacao(date("Ymd"));
			$Processo->setPrazo(transformaDataBanco($dtprazo));
			$Processo->setDtFim($dtfim);
			$Processo->setDtAviso($dtaviso);

			$Processo->setDtEscolhida(NULL);

			//se for escolhido SIM para pergunta "possui data de posse" 
			if($posse == 1){
				//se foi escolhida uma data válida (maior que zero)
				if($dtescolhida!==false && $dtescolhida>0){
					$Processo->setDtEscolhida(transformaDataTimestampBanco($dtescolhida));					
				}
			}
			
			// Chama a função de UPDATE que só resulta FALSE caso dê algum problema.
			$atualizou = $ProcessoDAO->update($Processo);
			//variáveis para Log:
			$responsavel_log="";
			$responsavel_num=0;
			$responsavel_array=array();

			if($atualizou){

					
					//verifica se alterou o MODO DO PROCESSO, se sim atualiza no BD
					if($infosprocesso["modo"]!=$modo){						
						$Processo->setModo($modo);
						$atualiza = $ProcessoDAO->updateModo($Processo);
					}
					//verifica se alterou o TIPO DA INSTITUIÇÃO OU se é Militar, atualiza BD
					if($infosprocesso["militar"]!=$militar || $militar==PROCESSO_MILITAR){
						$Processo->setMilitar($militar);
						$atualiza = $ProcessoDAO->updateMilitar($Processo);
						//se alterou para instituição "militar", altera modo para "sem eleições"
						if($militar==PROCESSO_MILITAR){
							$Processo->setModo(PROCESSOETAPA_SEMELEICOES);
							$atualiza = $ProcessoDAO->updateModo($Processo);
						}
					}
					

					//remove todos responsáveis escolhidos anteriormente para não reinserí-los
					$ResponsavelDAO->deleteFrom($idprocesso);
					//verifica se o usuário escolheu responsaveis
					for($i=1;$i<50;$i++){
						//se selecionou algum RESPONSAVEL, e esse Responsável não foi adicionado ainda (evita duplicados)
						if( isset($_POST["re_idusuario_".$i]) && $_POST["re_idusuario_".$i]>0 && !in_array($_POST["re_idusuario_".$i], $responsavel_array)){
							//armazenar informações
							$idusuario=$_POST["re_idusuario_".$i];

							// Instanciar as infos do Responsavel
							$Responsavel = new Responsavel();
							$Responsavel->setProcesso($idprocesso);
							$Responsavel->setUsuario($idusuario);
							// Chama a função de inserção que só resulta FALSE caso dê algum problema.
							$inseriu = $ResponsavelDAO->insert($Responsavel);
							if(!$inseriu){
								enviaMsg("erro","Erro","O processo foi cadastrado porém não foi possível salvar os responsáveis pelo mesmo");
								echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
								exit();
							}
							//adiciona ao array de responsáveis
							$responsavel_array[]=$idusuario;
							//armazena infos para acrescentar ao log:
							$responsavel_num++;
							$responsavel_log.=" Responsável ".$responsavel_num.": ID ".$idusuario.APP_LINE_BREAK;
						}
					}//fim for varrendo responsaveis


					//VERIFICA SE O DTPRAZO É DIFERENTE DO ANTERIOR, SE FOR DÁ UPDATE NO PRAZO PARA ALTERAR O FLAGPRAZO PARA "0"
					if($dtprazo!=exibeData($infosprocesso["dtprazo"])){
					    $atualizouDtPrazo = $ProcessoDAO->updateDtPrazo($Processo);
					}

					//SE usuário alterou etapa do processo, insere etapa em EtapaProcesso
					if($infosprocesso["idetapa"]!=$Processo->getEtapa()){
						$EtapaAlterada = new Etapa();
						$EtapaAlterada->setId($etapa);
						$infosetapaAlterada = $EtapaDAO->getOne($etapa);
						$EtapaAlterada->setProcesso($idprocesso);
						$EtapaAlterada->setUsuario1($_SESSION["USUARIO"]["idusuario"]);
						$EtapaAlterada->setAprova($infosetapaAlterada["aprova"]);
						$EtapaAlterada->setAprovaMsg(NULL);
						$EtapaAlterada->setPrazo($infosetapaAlterada["prazo"]);
						//atualiza etapa_processo
						$atualizou = $EtapaDAO->insertEtapaProcesso($EtapaAlterada);
					}

					//atualiza infos da etapa do processo e do processo em si
					$infosetapa = $EtapaDAO->getOne($etapa);
					$infosprocesso = $ProcessoDAO->getInfosCapa($Processo);
					//atribui infos ao objeto Processo!
					$Processo->setModo($infosprocesso["modo"]);
					$Processo->setMilitar($infosprocesso["militar"]);

					//verifica se o usuário escolheu trocar de etapa automaticamente e definiu uma data escolhida de posse
					if(isset($trocaetapa) && $trocaetapa==APP_FLAG_ACTIVE && $dtescolhida!==false && $dtescolhida>0){
						//atualiza a dtlembrete, que é 2 anos e 8 meses da data escolhida
						//dtfim === data de definição do lembrete
						//dtaviso = data em que aviso/lembrete foi enviado
						//atualiza DTFIM e zera DTAVISO
                    	$dtfim=date('Ymd', strtotime('+32 month', strtotime(transformaDataTimestampBanco($dtescolhida))));
                    	$dtaviso=NULL;
                    	$Processo->setDtFim($dtfim);
                    	$Processo->setDtAviso($dtaviso);
                    	$updateDtEscolhida = $ProcessoDAO->updateDtEscolhida($Processo);

						//exige infosprocesso (com getInfosCapa) & infosetapa (com getOne)
						//mais o models Etapa e Processo e seus DAOs
						$Etapa = new Etapa();
  						$Etapa->setId($infosprocesso["idetapa"]);
						//ENVIAR E-MAILS
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
			                  $EtapaCorreta->setPrazo($e["prazo"]);
			                  //insere o ETAPA_PROCESSO
			                  $inseriuEtapaProcesso = $EtapaDAO->insertEtapaProcesso($EtapaCorreta);
			                  //insere prazo da etapa para o processo
								//calcula o prazo de acordo com o valor em dias que vier do getPrazo
								if($EtapaCorreta->getPrazo()>0){
									$dtprazo=date('Ymd', strtotime('+'.$EtapaCorreta->getPrazo().' days'));
								}else{
									$dtprazo=0;
								}
								$Processo->setPrazo($dtprazo);
					            $atualizouDtPrazo = $ProcessoDAO->updateDtPrazo($Processo);
			                  //sai do foreach
			                  break; 
			                }
			              }
			            }

						//SE NECESSÁRIO atualizar historico da etapa atual (que passa agora a ser a anterior - a etapa do momento do envio do documento) em etapa_processo
						$dadosEtapaAnterior=$EtapaDAO->getLastEtapaProcesso($Processo);
							//se for uma etapa que está aguardando aprovação
								$EtapaAnterior = new Etapa();
								$EtapaAnterior->setId($dadosEtapaAnterior["idetapa_processo"]);
								$EtapaAnterior->setAprova(0);
								$EtapaAnterior->setAprovaMsg("Etapa atualizada automaticamente");
								$EtapaAnterior->setUsuario2($_SESSION["USUARIO"]["idusuario"]);
								//atualiza etapa no BD
								$atualizouAnterior = $EtapaDAO->updateEtapaProcesso($EtapaAnterior);
								//depois de atualizar no banco, atualiza o array com dados da etapa anterior
								$dadosEtapaAnterior=$EtapaDAO->getLastEtapaProcesso($Processo);
						//iniciar historico da nova etapa em etapa_processo
							$arrayEtapas = $EtapaDAO->getAll();
							//Objeto ETAPA para instanciar infos da proxima etapa
							$NovaEtapa = new Etapa();
							//para que ele passe para a próxima etapa pós troca de data, "enganamos a função" dizendo que esta etapa é do tipo PRINCIPAL
							$dadosEtapaAnterior["fluxo"]=ETAPA_PRINCIPAL;
          					$NovaEtapa = proximaEtapaProcesso2($arrayEtapas,$dadosEtapaAnterior,$Processo);
							//$NovaEtapa = proximaEtapaProcesso($arrayEtapas,$dadosEtapaAnterior,$idprocesso);

							//se o ID da NovaEtapa >= 0 é possível trocar de etapa, então insere a nova etapa
							if($NovaEtapa->getId() >= 0){
								//insere nova etapa no histórico do processo
								$inseriuEtapaProcesso = $EtapaDAO->insertEtapaProcesso($NovaEtapa);
								//atualiza processo
								$atualizouEtapa = $EtapaDAO->updateEtapa($NovaEtapa);
								//calcula o prazo de acordo com o valor em dias que vier do getPrazo
								if($NovaEtapa->getPrazo()>0){
									$dtprazo=date('Ymd', strtotime('+'.$NovaEtapa->getPrazo().' days'));
								}else{
									$dtprazo=0;
								}
								$Processo->setPrazo($dtprazo);
					            $atualizouDtPrazo = $ProcessoDAO->updateDtPrazo($Processo);
							}

					//fim troca de etapa automatica
					}

				
				//Recupera e altera dados para salvar os valores e não os IDS no histórico
				$UsuarioDAO = new UsuarioDAO();
				$dado=$UsuarioDAO->getOne($usuario);
				$Processo->setUsuario($dado["nome"]);
				
				$dado=$ProcessoDAO->getOneTipo($processotipo);
				$Processo->setProcessoTipo($dado["nome"]);

				$EtapaDAO = new EtapaDAO();
				$dado=$EtapaDAO->getOne($etapa);
				$Processo->setEtapa($dado["ordem"]." - ".$dado["nome"]);

				//salva OBS para registro no LOG:
				$obs_log=$Processo->toLog();
				//adiciona trecho dos responsáveis ao log
				if($responsavel_num>0){
					$obs_log.=APP_LINE_BREAK."Responsáveis:".APP_LINE_BREAK.$responsavel_log;
				}else{
					$obs_log.=APP_LINE_BREAK."Nenhum responsável definido";
				}
				
				//se inseriu o idprocesso
				//inseriu (quando houver) irregularidades e fiscais
				//o processo todo foi finalizado com sucesso => SALVAR NO HISTÓRICO
				$Historico = new Historico();
				$Historico->setAcao(LOG_EDIT_PRO);
				$Historico->setProcesso($idprocesso);
				$Historico->setObs(sqlTrataString($obs_log));
				$HistoricoDAO = new HistoricoDAO();
				$inseriuLog=$HistoricoDAO->insert($Historico);

				//arquivo de backend que pega os dados do nome do presidente cee e secretario da cee via POST e valida esses dados
				// foi inserido aqui para não interferir no histórico atualizado 2025//
				require_once("../control/edit_presidente_e_secretario.php");

				//se cair aqui é pq DEU TUDO CERTO!
				if($inseriuLog){
					
					enviaMsg("sucesso","Processo atualizado com sucesso");
					
					echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
					exit();
				//se cair aqui é pq não inseriu o log					
				}else{
					enviaMsg("erro","Erro","O processo foi atualizado porém não foi possível salvar o histórico");
					echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
					exit();
				}

			//se cair aqui é pq não atualizou o registro
			}else{
				enviaMsg("erro","Erro","O processo não pôde ser atualizado");
				echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
				exit();
			}
			


		//fim (IF Nº3)
		}else{

			enviaMsg("erro","Processo não atualizado","Os campos obrigatórios não foram preenchidos corretamente.");
			echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
			exit();

		}




	//fim do if validação dos campos obrigatórios passou (IF Nº2)
	}else{

		enviaMsg("erro","Processo não atualizado","Os campos obrigatórios não foram preenchidos corretamente. Tente novamente.");
		echo "<meta http-equiv=\"refresh\" content=\"0; url=index_doc.php?p=$idprocesso\">";
		exit();

	}

//fim do IF Nº1
//se não tiver sido enviado nenhum POST para página, exiba as informações abaixo:
}else{



require_once("../menu_topo.php");

//SUBMENU DE AÇÕES PROCESSO
if(isset($_GET["r"]) && !empty($_GET["r"]) && $_GET["r"] == "index_doc.php"){
	require_once('submenu_doc.php');
}else{
	require_once('submenu_pro.php');
}
//FIM SUBMENU DE AÇÕES

?>

<div id="conteudo_borda">
    <div  id="conteudo">
        <form id="edit_pro" name="edit_pro" action="edit_pro.php" method="post" class="form-horizontal">
        
        	<input type="hidden" name="p" id="p" value="<?php echo $idprocesso; ?>">

        	<div class="form-group">
	            <label for="numero" class="col-sm-12 control-label">Número do Processo</label>
			    <div class="col-sm-2">
			      <input type="text" class="form-control" id="numeroprocesso" name="numeroprocesso" value="<?php echo $infosprocesso["numero"]; ?>" disabled="disabled">
			      <br>
			    </div>
			</div>

            <div class="form-group">
            	<label for="processotipo" class="col-sm-12 control-label">Tipo do Processo</label>
			    <div class="col-sm-2">
			      <select id="processotipo" name="processotipo" class="form-control">
					<option value="-1">Selecione o tipo do processo&nbsp;&nbsp;&nbsp;&nbsp;</option>
					<?php
                    // Instanciar o DAO e retornar dados da tabela
                    $ProcessoDAO = new ProcessoDAO();
                    $result = $ProcessoDAO->getTipos();
                    if(sizeof($result)>0){
						for($i=0;$i<sizeof($result);$i++){		

								echo "<option value=\"".
								$result[$i]["idprocessotipo"]."\"";
								//se é igual ao dado recuperado do BD, manda vir já selecionado
								if($infosprocesso["idprocessotipo"]==$result[$i]["idprocessotipo"]){
									echo " selected=\"selected\" ";
								}
								echo ">".$result[$i]["nome"]."</option>";
						}
					}
					?>
                  </select>
			      <span id="helpBlock" class="help-block"><?php echo APP_MSG_REQUIRED; ?></span>
			    </div>			    
            </div>

            <div class="form-group">

            	<label for="etapa" class="col-sm-12 control-label">Etapa</label>
			    <div class="col-sm-2">
			      <select id="etapa" name="etapa" class="form-control">
					<option value="-1">Selecione a etapa atual do processo&nbsp;&nbsp;&nbsp;&nbsp;</option>
					<?php
                    // Instanciar o DAO e retornar dados da tabela TIPO
                    $EtapaDAO = new EtapaDAO();
                    //getAll(1) pois só queremos etapas do fluxo principal
                    $result = $EtapaDAO->getAll();
                    if(sizeof($result)>0){
						for($i=0;$i<sizeof($result);$i++){		



							echo "<option value=\"".$result[$i]["idetapa"]."\" ";
							//se é igual ao dado recuperado do BD, manda vir já selecionado
							if($infosprocesso["idetapa"]==$result[$i]["idetapa"]){
								echo " selected=\"selected\" ";
							}
							//se é do fluxo alternativo dá um espaço antes de exibir o número da ordem
							if($result[$i]["fluxo"]==1){
								echo ">&nbsp;&nbsp;&nbsp;";
							}else{
								echo ">";
							}
							echo $result[$i]["ordem"]." - ".$result[$i]["nome"]."</option>";
						}
					}
					?>
                  </select>
			      <span id="helpBlock" class="help-block"><?php echo APP_MSG_REQUIRED; ?></span>
			    </div>
			    
            </div>

            <div class="form-group">
            	<label class="col-sm-12 control-label">Prazo para conclusão da etapa</label>
	        	<div class="col-lg-2">
	        	<div class="input-group date">
		            <input type="text" class="form-control" id="dtprazo" name="dtprazo" placeholder="Indeterminado" value="<?php if($infosprocesso["dtprazo"]>0){ echo exibeData($infosprocesso["dtprazo"]); } ?>" maxlength="<?php echo USUARIO_DTEXPIRACAO_SIZE+2; ?>" autocomplete="off">
		            <span class="input-group-addon"><i class="glyphicon glyphicon-calendar"></i></span>
	        	</div>
	        	</div>
		    </div>

            <div class="form-group">

            	<label for="instituicao" class="col-sm-12 control-label">Instituição</label>
			    <div class="col-sm-2">
			      <select id="instituicao" name="instituicao" class="form-control">
					<option value="-1">Selecione a instituição&nbsp;&nbsp;&nbsp;&nbsp;</option>
					<?php
                    // Instanciar o DAO e retornar dados da tabela
                    $UsuarioDAO = new UsuarioDAO();
                    //chama GETALL passando valores:
                    //o caso o id do perfil de instituições, para retornar só usuários do perfil INSTITUIÇÃO
                    //o valor 1 para poder retornar mesmo instituições/usuários com o acesso expirado ao sistema
                    //seguido de um false para não permitir exibição de perfis excessão e o número 1 para ordenar pelo nome da instituição
                    $result = $UsuarioDAO->getAll(PERFIL_IDINSTITUICAO,1,false,1);
                    if(sizeof($result)>0){
						for($i=0;$i<sizeof($result);$i++){							
							if(!empty($result[$i]["nome_instituicao"])){
								echo "<option value=\"".
								$result[$i]["idusuario"]."\"";
								//se é igual ao dado recuperado do BD, manda vir já selecionado
								if($infosprocesso["idusuario"]==$result[$i]["idusuario"]){
									echo " selected=\"selected\" ";
								}
								echo ">".$result[$i]["nome_instituicao"]." (".$result[$i]["login"].")</option>";
							}
						}
					}
					?>
                  </select>
			      <span id="helpBlock" class="help-block"><?php echo APP_MSG_REQUIRED; ?></span>
			    </div>
			    
            </div>

            <div class="form-group">

            	<label for="modo" class="col-sm-12 control-label">Modo do processo</label>
			    <div class="col-sm-2">
			      <select id="modo" name="modo" class="form-control">
					<option value="-1">Selecione uma opção</option>
					<?php
						echo "<option value=\"".PROCESSOETAPA_NORMAL."\"";
						if($infosprocesso["modo"]==PROCESSOETAPA_NORMAL){
							echo " selected=\"selected\" ";
						}
						echo ">Normal (vê todas as etapas, até escolher outro modo)</option>";
						
						echo "<option value=\"".PROCESSOETAPA_COMELEICOES."\"";
						if($infosprocesso["modo"]==PROCESSOETAPA_COMELEICOES){
							echo " selected=\"selected\" ";
						}
						echo ">Com eleições</option>";

						echo "<option value=\"".PROCESSOETAPA_SEMELEICOES."\"";
						if($infosprocesso["modo"]==PROCESSOETAPA_SEMELEICOES){
							echo " selected=\"selected\" ";
						}
						echo ">Sem eleições</option>";
					?>
                  </select>
			      <span id="helpBlock" class="help-block"><?php echo APP_MSG_REQUIRED; ?></span>
			    </div>
			    
            </div>

            <div class="form-group">

            	<label for="militar" class="col-sm-12 control-label">Instituição Militar</label>
			    <div class="col-sm-2">
			      <select id="militar" name="militar" class="form-control">
					<option value="-1">Selecione uma opção</option>
					<?php
						echo "<option value=\"".PROCESSO_MILITAR."\"";
						if($infosprocesso["militar"]==PROCESSO_MILITAR){
							echo " selected=\"selected\" ";
						}
						echo ">Sim (modo do processo vira 'Sem eleições')</option>";
						echo "<option value=\"".PROCESSOETAPA_NORMAL."\"";
						if($infosprocesso["militar"]==PROCESSOETAPA_NORMAL){
							echo " selected=\"selected\" ";
						}
						echo ">Não</option>";						
					?>
                  </select>
			      <span id="helpBlock" class="help-block"><?php echo APP_MSG_REQUIRED; ?></span>
			    </div>
			    
            </div>
            

            <div class="form-group">

            	<label for="posse" class="col-sm-12 control-label">Data/Hora da posse já definida?</label>
			    <div class="col-sm-2">
			      <select id="posse" name="posse" class="form-control"  onChange="verificaValor(this.value,this.name);">
					<option value="-1">Selecione a resposta</option>
					<option value="1" <?php if($posse==1){ echo 'selected="selected"'; } ?>>Sim</option>
					<option value="2" <?php if($posse==2){ echo 'selected="selected"'; } ?>>Não</option>
                  </select>
			      <span id="helpBlock" class="help-block"><?php echo APP_MSG_REQUIRED; ?></span>
			    </div>
			    
            </div>
            

		        <div class="form-group well campoviaselect div_posse" id="posse_sim" style="<?php if($posse==1){ echo 'display:block !important'; } ?>">		        	
	            	<label for="dtescolhida" class="col-sm-12 control-label">Informe a data e hora escolhida</label>
		        	<div class="col-sm-3">
			        	<div class="input-group datetimepicker">
				            <span class="input-group-addon"><i class="glyphicon glyphicon-calendar" style="cursor:default !important;"></i></span>
				            <input value="<?php if(!empty($infosprocesso["dtescolhida"])){ echo exibeDataTimeStamp($infosprocesso["dtescolhida"]); } ?>" type="text" class="form-control" id="dtescolhida" name="dtescolhida" placeholder="Ex.: 20/08/2025 07:00" maxlength="<?php echo PROCESSO_DTPOSSE1_SIZE; ?>" autocomplete="off">					            
			        	</div>				        		        	
		        		<span id="helpBlock" class="help-block"><?php echo APP_MSG_REQUIRED; ?></span>
		        	</div>
		        	<label for="trocaetapa" class="col-sm-12 control-label">Avançar para a próxima etapa automaticamente?</label>
		        	<div class="col-sm-10">
			        	<select id="trocaetapa" name="trocaetapa" class="form-control">
			        		<option value="<?php echo APP_FLAG_INACTIVE; ?>" <?php if(!empty($infosprocesso["dtescolhida"])){ echo "selected=\"selected\""; } ?>>Não, mantenha o processo nesta etapa e não altere a data de envio do Lembrete de Renovação</option>
			        		<option value="<?php echo APP_FLAG_ACTIVE; ?>" <?php if(empty($infosprocesso["dtescolhida"]) || $infosprocesso["dtescolhida"]<=0){ echo "selected=\"selected\""; } ?>>Sim, avance para a próxima etapa e redefina uma data de envio do Lembrete de Renovação automaticamente</option>
			        	</select>
		        		<span id="helpBlock" class="help-block"><strong>Atenção:</strong> Escolhendo "Sim" e clicando em "Salvar" o processo irá para a próxima etapa e a Data programada para o Lembrete de Renovação será redefinida</span>
		        	</div>
		        </div>
		        
		       
		       	<div class="row">

				  <div class="col-md-6">
				  	<div class="form-group">
		            	<label class="col-sm-6 control-label">Lembrete Renovação: Data programada</label>
			        	<div class="col-sm-6">
			        	<div class="input-group date">
				            <input type="text" class="form-control" id="dtfim" name="dtfim" placeholder="Indefinida" value="<?php if($infosprocesso["dtfim"]>0){ echo exibeData($infosprocesso["dtfim"]); } ?>" maxlength="<?php echo USUARIO_DTEXPIRACAO_SIZE+2; ?>" autocomplete="off">
				            <span class="input-group-addon"><i class="glyphicon glyphicon-calendar"></i></span>
			        	</div>
			        	</div>
				    </div>
				  </div>

				  <div class="col-md-6">
				  	<div class="form-group">
		            	<label class="col-sm-6 control-label">Lembrete Renovação: Data de envio</label>
			        	<div class="col-sm-6">
			        	<div class="input-group datetimepicker">
				            <span class="input-group-addon"><i class="glyphicon glyphicon-calendar" style="cursor:default !important;"></i></span>
				            <input value="<?php if(!empty($infosprocesso["dtaviso"])){ echo exibeDataTimeStamp($infosprocesso["dtaviso"]); } ?>" type="text" class="form-control" id="dtaviso" name="dtaviso" title="Deixe sem nenhum valor para enviar o aviso novamente (será enviado somente se a data programada for menor ou igual a data atual)" placeholder="Não enviado" maxlength="<?php echo PROCESSO_DTPOSSE1_SIZE; ?>" autocomplete="off">					            
			        	</div>
			        	</div>
				    </div>
				  </div>

				</div>
		    


		    <div class="form-group well">
                <label class="col-lg-2 control-label">Responsável pelo processo</label>
                <div class="col-lg-10">
                    <button type="button" class="btn btn-info btn-sm add_re">
                        <span class="glyphicon glyphicon-plus" aria-hidden="true">
                        </span> Adicionar Responsável
                    </button>
                    <div id="responsavel"></div>
                

                <?php

                //carrega do BD os usuários "responsáveis"
				$UsuarioDAO = new UsuarioDAO();
				$usuariosresponsaveis = $UsuarioDAO->getAllResponsaveis();
				//gera inputs hidden aninhados com [] com os valores
				foreach ($usuariosresponsaveis as $t) {
					echo '<input type="hidden" name="usuariosresponsaveis[]" value="'.$t["idusuario"].'||||||'.$t["nome"].'" />';
				}

				//preenche a tela com os responsáveis já selecionados
				if($responsaveis!==false){
					if(sizeof($responsaveis)>0){
						for($i=0;$i<sizeof($responsaveis);$i++){
							//armazena em variaveis as informações do denunciado
							if(isset($responsaveis[$i]["idusuario"]) && !empty($responsaveis[$i]["idusuario"])){
								$idusuario=$responsaveis[$i]["idusuario"];
							}else{
								$idusuario="";
							}
							//instancia o script que adiciona e permite excluir este responsavel
							echo "	<script>
										$(document).ready(function(e) {
											var add = addResponsavel(".($i+24).", '".$idusuario."');
											$('#responsavel').prepend(add);
											$('.del_re').unbind('click');
											$('.del_re').bind('click',function(){
												var idre = $(this).attr('id').replace('del_','');
													$('.'+idre).slideUp();
													$('.'+idre).html('');
											});
										});
									</script>";
						}
					}
				}
				?>
				</div>
            </div>

			<form action="edit_presidente_e_secretario.php" method="POST" class="form-horizontal">
    <input type="hidden" name="idprocesso" value="<?= htmlspecialchars($idprocesso, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="form-group well" id="presidente-container">
        <label for="nomepresidentecee"><strong>Nome do Presidente da CEE:</strong></label>
        <input type="text" name="nomepresidentecee" id="nomepresidentecee" class="form-control" 
               placeholder="Digite o Nome do(a) Presidente da CEE"
               value="<?php echo htmlspecialchars(
                   isset($infosprocesso['nomepresidentecee']) && $infosprocesso['nomepresidentecee'] !== '' 
                   ? $infosprocesso['nomepresidentecee'] 
                   : 'nome.presidente', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

    <div class="form-group well" id="secretario-container">
        <label for="nomesecretariocee"><strong>Nome do Secretário da CEE:</strong></label>
        <input type="text" name="nomesecretariocee" id="nomesecretariocee" class="form-control" 
               placeholder="Digite o Nome do(a) Secretário(a) da CEE"
               value="<?php echo htmlspecialchars(
                   isset($infosprocesso['nomesecretariocee']) && $infosprocesso['nomesecretariocee'] !== '' 
                   ? $infosprocesso['nomesecretariocee'] 
                   : 'nome.secretario', ENT_QUOTES, 'UTF-8'); ?>">
    </div>

   <style> .form-horizontal {
    max-width: 800px; /* Define a largura desejada */
    width: 100%; /* Para manter a responsividade */
} </style>


            <div class="form-group" style="margin-top:40px;">
              <div class="col-lg-10" id="processo_<?php echo $idprocesso; ?>">
                <button type="reset" id="cancelar" class="btn btn-default index_pro.php">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Voltar&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</button>
                <button type="submit" class="btn btn-primary">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Salvar&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</button>
              </div>
            </div>
        </form>
	</div>
</div>
<?php

}//fim do else do "IF FOI ENVIADO ALGO - post"

include_once("../menu_rodape.php");

//else do verificaFuncaoUsuario(FUNCAO_PROCESSO_ADD)
}else{

		enviaMsg("erro","Acesso negado","Sem permissão de acesso, link quebrado ou dados inválidos");
		echo "<meta http-equiv=\"refresh\" content=\"0; url=index_pro.php\">";
		exit();

}
?>
