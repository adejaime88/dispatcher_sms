<?php

/*
dispacher.php - dispatcher de SMS
autor: Julio Cesar Fernandes de Souza
data: abr/2018
email: jcfsouza@yahoo.com.br
*/

require 'smtp.php';

abstract class LogLevel
{
    const Error = 0;
    const Warning = 1;
    const Info = 2;
}

 class SMSTemplate {

    private $content = "";
    private $config = "";
    private $configFile = "";
    private $templateTXTFile = "";
    
    private $sql = "";
    private $smsKey = null;
    private $sqlKey = null;
    private $smsKeys = null;
    private $smsValues = null;
    private $logKeys = null;    

    public function __construct($configFile)
    {        
        $this->configFile = $configFile;        
        $this->config = parse_ini_file($this->configFile, true);
        $smsConfig = $this->config["SMSConfig"];
    
        $this->templateTXTFile = $smsConfig["templateTXTFile"];
        $this->sql = $smsConfig["sql"];
        $this->smsKeys = $this->config["smsKeys"];

        // Configuracao dos campos do log
        $this->logKeys = $this->config["logKeys"];    
    }   

    function __destruct() {
    }    

    public function init(){
        $this->content = file_get_contents($this->GetTemplateFile()); 
        $this->content = str_replace("\n", "", $this->content);
        $this->content = str_replace("\r", "", $this->content);
        $this->content = trim($this->content); 
    }

    public function getSMS(){
        $this->init();

        $search = array();
        $replace = array();

        $i = 0;        
        while(true){
            // procura pela chave smsKey(n)
            $key = "smsKey".($i + 1);            
            if(!array_key_exists($key, $this->smsKeys)){
                break;                                                
            }

            // pega a chave
            $smsKey = $this->smsKeys[$key];

            // procura pelo campo da base
            $sqlK = "sqlKey".($i + 1);
            $sqlKey = $this->smsKeys[$sqlK];

            // verifica se o campo da base está em smsValues
            if(array_key_exists($sqlKey, $this->smsValues)){
                $search[$i] = $smsKey;        
                $replace[$i] = $this->smsValues[$sqlKey];
            }    
            $i++;
        }    
        
        $sms = str_replace($search, $replace, $this->content);
        return $sms;
    }

    public function SetKeyValue($sqlKey, $sqlValue){
        $this->smsValues[$sqlKey] = $sqlValue;    
    }
    
    protected function GetTemplateFile(){
        return $this->templateTXTFile;
    
    }

    public function GetSQLSelect(){
        return $this->sql;
    }

    public function GetKeys(){
        return $this->smsKeys;
    }

    public function GetLogKeys(){
        return $this->logKeys;
    }

 }

 // Dispatcher - classe principal de gerenciamento de SMS
 class Dispatcher {

    private $smsTemplate = null;
    private $countTeste = 0;
    private $query = null;
    private $conn = null;
    private $stid = null;
    private $smtp = null;
    private $configFile = null;
    private $smsConfig = null;
    private $dbServer = null;
    private $dbUser = null;
    private $dbPassword = null;
    private $dbDatabase = null;
    private $dbConnectionString = null;
    private $smsTelefoneTest = null;
    private $smsCodUsuario = null;
    private $smsCode = null;
    private $smsTipoEnvio = null;
    private $smsURL = null;
    private $logQuery = null;
    private $logConn = null;
    private $logStmt = null;
    private $logStmtNewId = null;
    private $logStmtNewSMSId = null;
    private $dbLogServer = null;
    private $dbLogUser = null;
    private $dbLogPassword = null;
    private $dbLogDatabase = null;   
    private $logDataset = null; 
    private $logLevel = null;

    public function __construct($configFile)
    {        
        $this->configFile = $configFile;
        $this->config = parse_ini_file("dispatcher.ini", true);

        // Configuracao da base origem dos dados Oracle
        $dbConnectionConfig = $this->config["DBConnectionConfig"];    
        $this->dbServer = $dbConnectionConfig["DBServer"];
        $this->dbUser = $dbConnectionConfig["DBUser"];
        $this->dbPassword = $dbConnectionConfig["DBPassword"];
        $this->dbDatabase = $dbConnectionConfig["DBDatabase"];
        $this->dbConnectionString = $dbConnectionConfig["DBConnectionString"];
        
        $this->smsTemplate = new SMSTemplate($this->configFile);

        // Configuracao da base destino para logSMS MySQL
        $dbLogConnectionConfig = $this->config["DBLogConnectionConfig"];    
        $this->dbLogServer = $dbLogConnectionConfig["DBServer"];
        $this->dbLogUser = $dbLogConnectionConfig["DBUser"];
        $this->dbLogPassword = $dbLogConnectionConfig["DBPassword"];
        $this->dbLogDatabase = $dbLogConnectionConfig["DBDatabase"];      

        // Configuracao do smtp
        $SMTPConfig = $this->config["SMTPConfig"]; 
        $this->smtp = new SMTP();        
        $this->smtp->userName = $SMTPConfig['userName'];
        $this->smtp->password = $SMTPConfig['password'];
        $this->smtp->server = $SMTPConfig['server'];
        $this->smtp->port = $SMTPConfig['port'];
        $this->smtp->SMTPSecure = $SMTPConfig['SMTPSecure'];
        $this->smtp->from = $SMTPConfig["from"];
        $this->smtp->fromName = $SMTPConfig["fromName"];

        // General Config
        $GeneralConfig = $this->config["GeneralConfig"];
        $this->logLevel = $GeneralConfig["logLevel"];

        if(array_key_exists("smsTelefoneTest", $GeneralConfig)){
            $this->smsTelefoneTest = $GeneralConfig["smsTelefoneTest"];
        } else {
            $this->smsTelefoneTest = null;
        }    

        if(array_key_exists("smsCodUsuario", $GeneralConfig)){
            $this->smsCodUsuario = $GeneralConfig["smsCodUsuario"];
        } else {
            $this->smsCodUsuario = null;
        }          
        
        if(array_key_exists("smsCode", $GeneralConfig)){
            $this->smsCode = $GeneralConfig["smsCode"];
        } else {
            $this->smsCode = null;
        } 

        if(array_key_exists("smsTipoEnvio", $GeneralConfig)){
            $this->smsTipoEnvio = $GeneralConfig["smsTipoEnvio"];
        } else {
            $this->smsTipoEnvio = null;
        } 

        if(array_key_exists("smsURL", $GeneralConfig)){
            $this->smsURL = $GeneralConfig["smsURL"];
        } else {
            $this->smsURL = null;
        }         

    }     

    function __destruct() {
        $this->smsTemplate = null;
        $this->smtp = null;
    }             

    function debug( $logLevel, $data ) {
        try {
            if($logLevel < $this->logLevel){
                $LogLevelStr = ["Error", "Warning", "Info"];
                $date = new DateTime();
                $log = date_format($date, "Y-m-d H:i:s.u")." - ".$LogLevelStr[$logLevel]." - ".$data."\r\n";
                echo $log;
                error_log($log, 3, "log.txt");
            }
        } catch(Exception $e){
        }
    }

    private function DBLogConnect(){        
        $this->debug(LogLevel::Info, "DBLogConnect - in");
        try{
            try{                
                $this->logConn = mysqli_connect($this->dbLogServer, $this->dbLogUser, $this->dbLogPassword, $this->dbLogDatabase);
                if (mysqli_connect_errno()) {
                    $this->debug(LogLevel::Error, "DBLogConnect - MySQL Connect LogSMS failed: ".mysqli_connect_error());
                    return false;
                }      
                mysqli_autocommit($this->logConn, FALSE);
                $this->logQuery = "UPDATE LogSMS SET originalId = ?, cliente = ?, tipoSMS = ?, dataVenc = ?, valor = ?, cpfcnpj = ?, nota = ?, cheque = ?, codbarras = ?, smsTransmitido = ?, smsObs = ?, dataSMS = ?, chaveRegistro = ?, telefone = ? WHERE id = ?";

                $this->logStmt = mysqli_prepare($this->logConn, $this->logQuery) or die(mysqli_error($this->logConn)); 
            
                $this->logStmtNewId = mysqli_prepare($this->logConn, 'insert into logSMS (id) select IfNull(max(log2.id), 0)+1 from logSMS log2') or die(mysqli_error($this->logConn)); 

                $this->logStmtNewSMSId = mysqli_prepare($this->logConn, 'update smsCounter set id = id + 1') or die(mysqli_error($this->logConn)); 
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBLogConnect - MySQL Error: ".$e->getMessage()); 
                return false;
            }
        } finally {   
            $this->debug(LogLevel::Info, "DBLogConnect - out");
        }
        return true;
    }

    private function DBConnect(){        
        $this->debug(LogLevel::Info, "DBConnect - in");
        try {
            try {        
                $this->conn = oci_connect($this->dbUser, $this->dbPassword, $this->dbConnectionString);
                if (!$this->conn) {
                    $e = oci_error();
                    $msg = $e['message'];
                    $this->debug(LogLevel::Error, "DBConnect - Oracle Connect failed: ".$msg);
                    return false;
                }    
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBConnect - Oracle Error: ".$e->getMessage()); 
                return false;
            }            
        } finally {
            $this->debug(LogLevel::Info, "DBConnect - out");    
        }    
        return true;
    }
 
    private function DBLogDisconnect(){
        $this->debug(LogLevel::Info, "DBLogDisconnect - in");
        try {
            try {
                if($this->logConn){
                    mysqli_close($this->logConn);
                }
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBLogDisconnect - MYSQL Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "DBLogDisconnect - out");
        }
        return true;
    }
    
    private function DBDisconnect(){
        $this->debug(LogLevel::Info, "DBDisconnect - in");
        try {
            try {
                if($this->stid){
                    oci_free_statement($this->stid);
                }
                if($this->conn){
                    oci_close($this->conn);
                }
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBDisconnect - Oracle Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "DBDisconnect - out"); 
        }
        return true;
    }

    private function GetSMSTemplate(){    
        return $this->smsTemplate;
    }

    private function MakeSMS(){
        $this->debug(LogLevel::Info, "MakeSMS - in");
        try {
            try {
                $template = $this->GetSMSTemplate();
                $smsKeys = $template->GetKeys();

                $i = 1;
                while(true){
                    if(!array_key_exists("sqlKey$i", $smsKeys)){
                        break;    
                    }
                    $sqlKey = $smsKeys["sqlKey$i"];
                    $sqlvalue = $this->dataset[$sqlKey];
                    $template->SetKeyValue($sqlKey, $sqlvalue); 
                    $i++; 
                }
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "MakeSMS - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "MakeSMS - out");
        }
        return true;
    }    

    private function SendSMS(&$gravaLog, &$erro, &$smsTransmitido){
        $this->debug(LogLevel::Info, "SendSMS - in");
        try {
            try {
                $template = $this->GetSMSTemplate();
                $logKeys = $template->GetLogKeys();

                $sms = $template->getSMS();
                $iLen = strlen($sms);
                if($iLen > 160){
                    $gravaLog = true;
                    $erro = 'Tamanho da mensagem excedeu 160 caracteres. Total de '.$iLen.' caracteres utilizados.';
                    $smsTransmitido = false;
                    return false;                    
                }
                
                $sqlTelefone = trim($logKeys["telefone"]);
                if($sqlTelefone != ''){
                    $telefone = $this->dataset[$sqlTelefone];
                } else {
                    $telefone = '';
                }

                if($this->smsTelefoneTest && $this->smsTelefoneTest != ""){
                    $telefone = $this->smsTelefoneTest;
                }                
                
                /* grava em arquivo
                $name = 'SMS.txt';
                $file = fopen($name, 'a');
                fwrite($file, $sms);
                fclose($file);*/

               // $this->debug(LogLevel::Info, "SMS sent teste - telefone: ".$telefone);
               // return true;
        
                $smsId = $this->DBLogGetNewSMSId(); // não pode repetir
                       
                $url = $this->smsURL.'/integracao.do?account='.$this->smsCodUsuario.'&code='.urlEncode($this->smsCode).'&type=E&id='.$smsId.'&dispatch=send&msg='.urlEncode($sms).'&to='.$telefone.'&tipoEnvio='.$this->smsTipoEnvio;                
                
                /* grava em arquivo
                $this->debug(LogLevel::Info, "SMS sent teste - url:".$url);

                $gravaLog = true;
                $erro = '';
                $smsTransmitido = true;

                return true;*/
                
                // Transmite o SMS via HTTP
                $ch = curl_init();
                try {
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_HEADER, 0);
                    $result = curl_exec($ch);

                    try{
                        $this->debug(LogLevel::Info, "SMS sent - result: ".$result." - url:".$url);
                    } catch(Exception $e) {                        
                    }
                    
                    if(!$result){ 
                        $gravaLog = true;
                        //$info = curl_getinfo($ch);
                        //if($info['http_code']==...){
                        //}
                        $erro = curl_error($ch);
                        $smsTransmitido = false;
                        return false;                            
                    } 
                    $gravaLog = true;
                    $erro = '';
                    $smsTransmitido = true;
                    return true;
                } finally {
                    curl_close($ch);
                } 

            } catch(Exception $e){
                $this->debug(LogLevel::Error, "SendSMS - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "SendSMS - out");    
        }

        return true;
    }    

    // Verifica se o sms já foi transmitido
    private function VerifySMS(){        
        $this->debug(LogLevel::Info, "VerifySMS - in");
        try {
            try {
                $template = $this->GetSMSTemplate();
                $smsKeys = $template->GetKeys();
                $logKeys = $template->GetLogKeys();

                $tipoSMS = trim($logKeys["tipoSMS"]);

                $sqlOriginalId = trim($logKeys["originalId"]);
                if($sqlOriginalId != ''){
                    $originalId = $this->dataset[$sqlOriginalId];
                } else {
                    $originalId = '';
                }    
                
                $sqlChaveRegistro = trim($logKeys["chaveRegistro"]);
                if($sqlChaveRegistro != ''){
                    $chaveRegistro = $this->dataset[$sqlChaveRegistro];
                } else {
                    $chaveRegistro = '';
                }

                $originalIdValue = $this->dataset[$sqlOriginalId];

                $select = 'select 1 from logSMS where tipoSMS = "'.$tipoSMS.'" and originalId = "'.$originalIdValue.'" and chaveRegistro = "'.$chaveRegistro.'"';

                $this->debug(LogLevel::Info, "VerifySMS - Select: ".$select);

                $logQuery = mysqli_query($this->logConn, $select);

                $num_rows = mysqli_num_rows($logQuery);

                if($num_rows > 0){
                    $this->debug(LogLevel::Info, "VerifySMS - SMS já processado");
                    return false;
                }
                $this->debug(LogLevel::Info, "VerifySMS - SMS ainda não processado");
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "VerifySMS - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "VerifySMS - out");
        }
        return true;        
    }

    private function ProcessSMS(&$gravaLog, &$erro, &$smsTransmitido){
        $gravaLog = false;
        $erro = '';
        $smsTransmitido = 0;

        if(!$this->VerifySMS()){
            return false;
        }
        if(!$this->MakeSMS()){
            return false;    
        }
        if(!$this->SendSMS($gravaLog, $erro, $smsTransmitido)){
            return false;
        }
        return true;
    }

    private function DBGetSelect(){
        $template = $this->GetSMSTemplate();
        $select = $template->GetSQLSelect();
        return $select;
    }

    private function DBOpen(){
        $this->debug(LogLevel::Info, "DBOpen - in");
        try {
            try {
                $select = $this->DBGetSelect();

                $this->stid = oci_parse($this->conn, $select);
                if (!$this->stid) {
                    $e = oci_error($this->conn);
                    printf("Error: %s\n", $e['message']);
                }
                
                // Perform the logic of the query
                $this->query = oci_execute($this->stid);
                if (!$this->query) {
                    $e = oci_error($this->stid);
                    printf("Error: %s\n", $e['message']);
                }
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBOpen - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "DBOpen - out");    
        }

        return true;
    }

    private function DBFirst(){
        $this->debug(LogLevel::Info, "DBFirst - in");
        try {
            try {
                $this->dataset = oci_fetch_array($this->stid, OCI_ASSOC + OCI_RETURN_NULLS); 
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBFirst - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "DBFirst - out");
        }
        return true;
    }

    private function DBNext(){
        $this->debug(LogLevel::Info, "DBNext - in");
        try {
            try {
                $this->dataset = oci_fetch_array($this->stid, OCI_ASSOC + OCI_RETURN_NULLS); 
            } catch(Exception $e){
                $this->debug(LogLevel::Error, "DBNext - Error: ".$e->getMessage()); 
                return false;
            }
        } finally {
            $this->debug(LogLevel::Info, "DBNext - out");
        }
        return true;
    }

    private function DBEof(){
        return $this->dataset == null;    
    }

    private function DBLogGetNewSMSId(){
        $this->debug(LogLevel::Info, "DBLogGetNewSMSId - in");
        try {
            mysqli_begin_transaction($this->logConn);
            try {            
                if(!mysqli_stmt_execute($this->logStmtNewSMSId)){  
                    $this->debug(LogLevel::Error, "smsCounter insert error: " + mysqli_error($this->logConn));
                    return -1;
                }                            
                $this->logQuery = mysqli_query($this->logConn, 'select id from smscounter');
                if (!$this->logQuery) {
                    printf("Error: %s\n", mysqli_error($this->logConn));
                    return false;
                }    
                $this->logDataset = mysqli_fetch_array($this->logQuery, MYSQLI_BOTH);
                $result = $this->logDataset["id"];
                mysqli_commit($this->logConn);
                return $result;
            } catch(Exception $e) {
                $this->debug(LogLevel::Error, "DBLogGetNewSMSId - Error: ".$e->getMessage()); 
                mysqli_rollback($this->logConn);                
                return -1;
            }       
        } finally {
            $this->debug(LogLevel::Info, "DBLogGetNewSMSId - out");    
        }     
    }

    private function DBLogGetNextId(){
        $this->debug(LogLevel::Info, "DBLogGetNextId - in");
        try {
            mysqli_begin_transaction($this->logConn);
            try {            
                if(!mysqli_stmt_execute($this->logStmtNewId)){  
                    $this->debug(LogLevel::Error, "logSMS insert error: " + mysqli_error($this->logConn));
                    return -1;
                }            
                $this->logQuery = mysqli_query($this->logConn, 'select max(id) as maxid from logSMS');
                if (!$this->logQuery) {
                    printf("Error: %s\n", mysqli_error($this->logConn));
                    return false;
                }    
                $this->logDataset = mysqli_fetch_array($this->logQuery, MYSQLI_BOTH);
                $result = $this->logDataset["maxid"];
                mysqli_commit($this->logConn);
                return $result;
            } catch(Exception $e) {
                $this->debug(LogLevel::Error, "DBLogGetNextId - Error: ".$e->getMessage()); 
                mysqli_rollback($this->logConn);                
                return -1;
            }       
        } finally {
            $this->debug(LogLevel::Info, "DBLogGetNextId - out");    
        }     
    }

    private function DBLogUpdate($erro, $smsTransmitido){              
        $this->debug(LogLevel::Info, "DBUpdate - in");        
        try {
            try {                                                 
                if(!mysqli_stmt_bind_param($this->logStmt, 'issssssssissssi', $originalId, $cliente, $tipoSMS, $dataVenc, $valor, $cpfcnpj, $nota, $cheque, $codbarras, $smsTransmitido, $smsObs, $dataSMS, $chaveRegistro, $telefone, $id)){
                    return false;
                }    

                $template = $this->GetSMSTemplate();
                $logKeys = $template->GetLogKeys();
                $id = $this->DBLogGetNextId();
                if($id < 0){
                    return false;
                }
                $sqlOriginalId = trim($logKeys["originalId"]);
                if($sqlOriginalId != ''){
                    $originalId = $this->dataset[$sqlOriginalId];
                } else {
                    $originalId = '';
                }

                $sqlCliente = trim($logKeys["cliente"]);
                if($sqlCliente != ''){
                    $cliente = $this->dataset[$sqlCliente];
                } else {
                    $cliente = '';
                }

                $tipoSMS = trim($logKeys["tipoSMS"]);
                $dataSMS = date("Y-m-d H:i:s");

                $sqlDataVenc = trim($logKeys["dataVenc"]);
                if($sqlDataVenc != ''){
                    $dataVenc = $this->dataset[$sqlDataVenc];
                } else {
                    $dataVenc = '';
                }
                
                if($dataVenc != ''){
                    $dt = DateTime::createFromFormat('d/m/y', $dataVenc);
                    if(!$dt){
                        $dt = DateTime::createFromFormat('d/m/Y', $dataVenc);
                    }
                    if($dt){
                        $dataVenc = $dt->format('Y-m-d');
                    } else {
                        $dataVenc = '';
                        $erro = $erro.' - dataVenc com formato inválido';
                    }
                }

                $sqlValor = trim($logKeys["valor"]);
                if($sqlValor != ''){
                    $valor = (float) $this->dataset[$sqlValor];
                } else {
                    $valor = 0.0;
                }
                $sqlCpfcnpj = trim($logKeys["cpfcnpj"]);
                if($sqlCpfcnpj != ''){
                    $cpfcnpj = $this->dataset[$sqlCpfcnpj]; 
                } else {
                    $cpfcnpj = '';
                }
                $sqlNota = trim($logKeys["nota"]);
                if($sqlNota != ''){
                    $nota = $this->dataset[$sqlNota];
                } else {
                    $nota = '';
                }
                $sqlCheque = trim($logKeys["cheque"]);
                if($sqlCheque != ''){
                    $cheque = $this->dataset[$sqlCheque];
                } else {
                    $cheque = '';
                }
                $sqlCodBarras = trim($logKeys["codbarras"]);
                if($sqlCodBarras != ''){
                    $codbarras = $this->dataset[$sqlCodBarras];
                } else {
                    $codbarras = '';
                }

                $smsObs = $erro;
                
                $sqlChaveRegistro = trim($logKeys["chaveRegistro"]);
                if($sqlChaveRegistro != ''){
                    $chaveRegistro = $this->dataset[$sqlChaveRegistro];
                } else {
                    $chaveRegistro = '';
                }
                
                $sqlTelefone = trim($logKeys["telefone"]);
                if($sqlTelefone != ''){
                    $telefone = $this->dataset[$sqlTelefone];
                } else {
                    $telefone = '';
                }                

                $this->debug(LogLevel::Info, "DBUpdate - originalId: ".$originalId.
                    " - id: ".$id.    
                    " - cliente: ".$cliente.
                    " - tipoSMS: ".$tipoSMS.
                    " - dataSMS: ".$dataSMS.
                    " - dataVenc: ".$dataVenc.
                    " - valor: ".$valor.
                    " - cpfcnpj: ".$cpfcnpj.
                    " - nota: ".$nota.
                    " - cheque: ".$cheque.
                    " - codbarras: ".$codbarras.
                    " - smsTransmitido: ".$smsTransmitido.
                    " - smsObs: ".$smsObs.
                    " - telefone: ".$telefone.
                    " - chaveRegistro: ".$chaveRegistro);

                if(!mysqli_stmt_execute($this->logStmt)){
                    $error = mysqli_error($this->logStmt);
                    $this->debug(LogLevel::Error, "DBUpdate - logSMS insert error: ".$error);
                    return false;
                }

                mysqli_commit($this->logConn);

            } catch(Exception $e) {
                $this->debug(LogLevel::Error, "DBUpdate - Error: ".$e->getMessage());               
                return false;
            }  
        } finally {
            $this->debug(LogLevel::Info, "DBUpdate - out");     
        }
        return true;
    }

    public function execute(): void{
        $this->debug(LogLevel::Info, "execute - in");
        try {                    
            try {
                if(!$this->DBConnect()){
                    return;
                }
                if(!$this->DBLogConnect()){                    
                    return;
                }
                if(!$this->DBOpen()){
                    return;
                }
                $countTeste = 0;
                $this->DBFirst();
                while(!$this->DBEof() && $countTeste < 10000){ // teste somente processa um sms
                    $this->debug(LogLevel::Info, "execution count: $countTeste");
                    $gravaLog = false;
                    $erro = '';
                    $smsTransmitido = 0;
                    $this->ProcessSMS($gravaLog, $erro, $smsTransmitido);
                    if($gravaLog){
                        $this->DBLogUpdate($erro, $smsTransmitido);
                    }
                    $this->DBNext();
                    $countTeste++;
                }      
            } catch(Exception $e) {
                $this->debug(LogLevel::Error, "execute - Error: ".$e->getMessage());               
            }       
        } finally {            
            $this->DBDisconnect();
            $this->DBLogDisconnect();
            $this->debug(LogLevel::Info, "execute - out");
        }
     }
 }

  date_default_timezone_set("America/Sao_Paulo");
  
  if(count($argv) < 2){      
      throw new Exception("Invalid Parameters: Syntax: dispatcher.php <config.ini>");
  } 

  $disp = new Dispatcher($argv[1]);
  $disp->execute();

  $disp = null;

?>