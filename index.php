<?php 


////// SI LLEGASE A TENER UNA DUDA ESPECIFICA DEL FUNCIONAMIENTO DEL CODIGO FAVOR DE CONSULTAR EL MANUAL


function loadDatabaseSettings($path){
    $string = file_get_contents($path);
    $json_a = json_decode($string, true);
    return $json_a;
}


$dbcnf = loadDatabaseSettings('../info/db.json');
function getToken(){
	
	$fecha = date_create();
	$tiempo = date_timestamp_get($fecha);
	
	$numero = mt_rand();

	$cadena = ''.$numero.$tiempo;
	
	$numero2 = mt_rand();
	
	$cadena2 = ''.$numero.$tiempo.$numero2;
	
	$hash_sha1 = sha1($cadena);

	$hash_md5 = md5($cadena2);
	return substr($hash_sha1,0,20).$hash_md5.substr($hash_sha1,20);
}

require 'vendor/autoload.php';
$f3 = \Base::instance();


$f3->route('POST /Registro',
	function($f3) {
		$dbcnf = loadDatabaseSettings('../info/db.json');
		$db=new DB\SQL(
			'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
			$dbcnf['user'],
			$dbcnf['password']
		);
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		/////////////
		$R = array_key_exists('uname',$jsB) && array_key_exists('email',$jsB) && array_key_exists('password',$jsB);
		
		if (!$R){
			echo '{"R":-1}';
			return;
		}
		
		try {
			$R = $db->prepare('INSERT INTO Usuario (uname, email, password) VALUES (?, ?, ?)');
			$R->bindParam(1, $jsB['uname']);
			$R->bindParam(2, $jsB['email']);
			$hashedPassword = password_hash($jsB['password'], PASSWORD_DEFAULT);
			$R->bindParam(3, $hashedPassword);
			$R->execute();
		} catch (Exception $e) {
			error_log("Error: " . $e->getMessage()); 
			echo '{"R":-2}';
			return;
		}
		echo "{\"R\":0,\"D\":".var_export($R,TRUE)."}";
	}
);


$f3->route('POST /Login',
	function($f3) {
		$dbcnf = loadDatabaseSettings('../info/db.json');
		$db=new DB\SQL(
			'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
			$dbcnf['user'],
			$dbcnf['password']
		);
		$db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		/////////////
		$R = array_key_exists('uname',$jsB) && array_key_exists('password',$jsB);
		
		if (!$R){
			echo '{"R":-1}';
			return;
		}
	
		try {
			$R = $db->prepare('SELECT id, password FROM Usuario WHERE uname = :uname');
			$R->bindValue(':uname', $jsB['uname']);
			$R->execute();
		} catch (Exception $e) {
			error_log("Error: " . $e->getMessage()); 
			echo '{"R":-2}';
			return;
		}

		if ($R->rowCount() == 0) {
			echo '{"R":-3}';
			return;
		}
		$userData = $R->fetch(PDO::FETCH_ASSOC);
		$storedHash = $userData['password'];
		if (password_verify($jsB['password'], $storedHash)) {
			
			$T = getToken();
			$db->exec('Delete from AccesoToken where id_Usuario = "'.$userData['id'].'";');
			$query = 'INSERT INTO AccesoToken (id_Usuario, token, fecha) VALUES (:id, :token, NOW())';
			$R = $db->prepare($query);
			$R->bindValue(':id', $userData['id']);
			$R->bindValue(':token', $T);
			$R->execute();
			echo "{\"R\":0,\"D\":\"".$T."\"}";
		} else {
			
			
			echo '{"R":-4}';
			return;
		}
	});

$f3->route('POST /Imagen', function($f3) {
  
    $Cuerpo = $f3->get('BODY');
    $jsB = json_decode($Cuerpo, true);

    
    if (!isset($jsB['token']) || !isset($jsB['name']) || !isset($jsB['data']) || !isset($jsB['ext'])) {
        echo '{"R":-1}';
        return;
    }

    $dbcnf = loadDatabaseSettings('../info/db.json');
    $db = new DB\SQL(
        'mysql:host=localhost;port=' . $dbcnf['port'] . ';dbname=' . $dbcnf['dbname'],
        $dbcnf['user'],
        $dbcnf['password']
    );

    
    $token = $jsB['token'];
    $query = 'SELECT id_Usuario FROM AccesoToken WHERE token = :token';
    $stmt = $db->prepare($query);
    $stmt->bindValue(':token', $token);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo '{"R":-2}';
        return;
    }

    $userId = $result['id_Usuario'];


    if (!isset($jsB['id_Usuario']) || $userId !== $jsB['id_Usuario']) {
        echo '{"R":-3}';
        return;
    }


    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileType = mime_content_type('data://' . substr($jsB['data'], strpos($jsB['data'], ',') + 1));

    if (!in_array($fileType, $allowedTypes)) {
        echo '{"R":-5}';
        return;
    }

  
    $imageName = $jsB['name'];
    $imageData = base64_decode($jsB['data']);
    $imageExt = $jsB['ext'];
    $imagePath = 'tmp/' . $imageName . '.' . $imageExt;

    if (!file_put_contents($imagePath, $imageData)) {
        echo '{"R":-6}';
        return;
    }

   
    $query = 'INSERT INTO Imagen (name, ruta, id_Usuario) VALUES (:name, :ruta, :idUsuario)';
    $stmt = $db->prepare($query);
    $stmt->bindValue(':name', $imageName);
    $stmt->bindValue(':ruta', $imagePath);
    $stmt->bindValue(':idUsuario', $userId);

    if (!$stmt->execute()) {
        echo '{"R":-7}';
        return;
    }

 
    echo '{"R":0}';
});


$f3->route('POST /Descargar',
 function($f3) {
	 $dbcnf = loadDatabaseSettings('db.json');
	 $db = new DB\SQL(
		 'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
		 $dbcnf['user'],
		 $dbcnf['password']
	 );
	 $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	 
	
	 $body = $f3->get('BODY');
	 $requestData = json_decode($body, true);
	 
	
	 if (!isset($requestData['token']) || !isset($requestData['id'])) {
		 echo '{"R":-1}';
		 return;
	 }
	 
	 
	 $token = $requestData['token'];
	 $idImagen = $requestData['id'];
	 
	
	 try {
		 $query = 'SELECT id_Usuario FROM AccesoToken WHERE token = :token';
		 $stmt = $db->prepare($query);
		 $stmt->bindParam(':token', $token);
		 $stmt->execute();
		 $result = $stmt->fetch(PDO::FETCH_ASSOC);
		 
		 if (!$result) {
			 echo '{"R":-2}';
			 return;
		 }
		 
		 $idUsuario = $result['id_Usuario'];
		 
	 } catch (Exception $e) {
		 error_log("Error: " . $e->getMessage()); 
		 echo '{"R":-2}';
		 return;
	 }
	 
	
	 try {
		 $query = 'SELECT id_Usuario FROM Imagen WHERE id = :idImagen';
		 $stmt = $db->prepare($query);
		 $stmt->bindParam(':idImagen', $idImagen);
		 $stmt->execute();
		 $result = $stmt->fetch(PDO::FETCH_ASSOC);
		 
		 if (!$result || $result['id_Usuario'] != $idUsuario) {
			 echo '{"R":-3}';
			 return;
		 }
		 
	 } catch (Exception $e) {
		 error_log("Error: " . $e->getMessage()); 
		 echo '{"R":-3}';
		 return;
	 }
	 
	 
	 try {
		 $query = 'SELECT name, ruta FROM Imagen WHERE id = :idImagen';
		 $stmt = $db->prepare($query);
		 $stmt->bindParam(':idImagen', $idImagen);
		 $stmt->execute();
		 $result = $stmt->fetch(PDO::FETCH_ASSOC);
		 
		 if (!$result) {
			 echo '{"R":-4}';
			 return;
		 }
		 
		 $name = $result['name'];
		 $ruta = $result['ruta'];
		 
	 } catch (Exception $e) {
		 error_log("Error: " . $e->getMessage()); 
		 echo '{"R":-4}';
		 return;
	 }
	 
	 
	 $web = \Web::instance();
	 $info = pathinfo($ruta);
	 $web->send($ruta, NULL, 0, TRUE, $name.'.'.$info['extension']);
 }
);


$f3->run();


?>
