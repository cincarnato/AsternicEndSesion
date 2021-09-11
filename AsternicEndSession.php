<?php

const DB_HOST = "localhost:3306";
const DB_NAME = "asternic";
const DB_USER = "root";
const DB_PASS = "root.123";

const EVENT_START_SESSION = "START SESSION";
const EVENT_END_CALL = "END CALL";
const EVENT_END_SESSION = "END SESSION";



//CONEXION MYSQL
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($mysqli->connect_errno) {
    echo "Fallo al conectar a MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
}

echo "DB HOST INFO: ".$mysqli->host_info . "\n";


//Verifico si se requiere fecha especifica

$dateRegex = "/^\d{4}\-(0[1-9]|1[012])\-(0[1-9]|[12][0-9]|3[01])$/";

if(isset($argv[1])){
    if(preg_match($dateRegex,$argv[1])){
            $dateFrom = new \DateTime($argv[1]);

    }else{
        throw new \Exception("El argumento uno debe ser una fecha con formato YYYY-MM-DD");
    }
}else{
    $dateFrom = new \DateTime("now");
    $dateFrom->setTime(0, 0,0);
    $dateFrom->modify('-1 day');
}
$dateTo = clone $dateFrom;
$dateTo->modify('+1 day');


//1. BUSCO LAS START SESIONES
echo "Procesar END SESION desde: ".$dateFrom->format("Y-m-d")." hasta: ".$dateTo->format("Y-m-d"). "\n";

$queryStartSession = 'SELECT id,agent,datetime,queue,event FROM agent_activity where event = "'.EVENT_START_SESSION.'" and datetime >= "'.$dateFrom->format("Y-m-d").'" and datetime < "'.$dateTo->format("Y-m-d").'" GROUP BY agent';
//echo "QUERY START SESSION: ".$queryStartSession. "\n";
$resultStartSession = $mysqli->query($queryStartSession);


while ($row = $resultStartSession->fetch_assoc()) {
    //echo " id = " . $row['id'] . " | agent = " . $row['agent'] . " | datetime = " . $row['datetime'] . " | event = " . $row['event'] . "\n";
    //POR CADA AGENT BUSCO EL ULTIMO EVENTO END CALL
    $agent = $row['agent'];
    $queue = $row['queue'];
    $queryEndCall = 'SELECT id,agent,datetime, event FROM agent_activity where agent = "'.$agent.'" and  event = "'.EVENT_END_CALL.'" and datetime >= "'.$dateFrom->format("Y-m-d").'" and datetime < "'.$dateTo->format("Y-m-d").'" ORDER BY datetime DESC LIMIT 1';
    //echo "QUERY END CALL: ".$queryEndCall. "\n";
    $resultEndCall = $mysqli->query($queryEndCall);
    $rowEndCall = $resultEndCall->fetch_assoc();

    if($rowEndCall){
        $sessionDateFrom = new \DateTime($row['datetime']);
        $sessionDateTo = new \DateTime($rowEndCall['datetime']);
        $sessionTime = $sessionDateTo->getTimestamp() - $sessionDateFrom->getTimestamp();

        //POR CADA AGENT INSERTO UN END SESSION
        $queryEndSession= 'INSERT INTO agent_activity (datetime,queue, agent, event, lastedforseconds) VALUES ("'.$sessionDateTo->format("Y-m-d H:i:s").'", "'.$queue.'","'.$agent.'", "'.EVENT_END_SESSION.'", '.$sessionTime.')';
        $resultEndSession = $mysqli->query($queryEndSession);

        echo '{"agent": "'.$agent.'", "from": "'.$sessionDateFrom->format("Y-m-d H:i:s").'", "to": "'.$sessionDateTo->format("Y-m-d H:i:s").'", "time": "'. $sessionTime .'", "insert": '.($resultEndSession ? 'true' : 'false' ).' }'.PHP_EOL.PHP_EOL;

    }

}





