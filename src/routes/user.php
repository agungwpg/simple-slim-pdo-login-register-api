<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';


$app = new \Slim\App;

//get all user data
$app->get('/api/users', function (Request $request, Response $response, array $args) {
    $sql =  "SELECT * FROM users";
    try{
        $db = new database();
        $db = $db->conn();
        $stmt = $db->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
        $response = array('text' => "success", 'data' => $users);
        echo json_encode($response);
    }
    catch(PDOException $e){
        $response = array('error' => ['text' => $e->getMessage()]);
        echo json_encode($response);
    }
});

//user login
$app->post('/api/users/login',function(Request $request, Response $response){
    $username = $request->getParam('username');
    $password = $request->getParam('password');
    $sql = "SELECT id,name,email,password,username,isEmailConfirmed,avatar FROM users WHERE username=:u";
    $db = new database();
    $db = $db->conn();
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':u',$username);
    $stmt->execute();
    $data = $stmt->fetch(PDO::FETCH_OBJ);
    if($stmt->rowCount() > 0 )
    {
        if($data->isEmailConfirmed == 1)
        {
            if(password_verify($password,$data->password))
            {
                $user = new stdClass();
                $user->id_user = $data->id;
                $user->username = $data->username;
                $user->name = $data->name;
                $user->email = $data->email;
                $user->avatar = $data->avatar;
                $response = array('response' => ['message' => 'login success' , 'status' => 'success','data' => $user]);
                return json_encode($response);
            }
            else
            {
                $response = array('response' => ['message' => 'invalid username or password', 'status' => 'failed']);
                return json_encode($response);
            }
        }
        else
        {
            $response = array('response' => ['message' => 'please confirm ur email account first', 'status' => 'failed']);
            return json_encode($response);
        }
    }
    else
    {
        $response = array('response' => ['message' => 'there is no username', 'status' => 'failed']);
        return json_encode($response);
    }
});

//user confirmation email
$app->get('/api/users/confirm_email',function(Request $request, Response $response){
    $sql = "UPDATE users SET isEmailConfirmed = '1' WHERE email=:email AND token=:token";
    $db =  new database();
    $db = $db->conn();
    $stmt = $db->prepare($sql);
    $email = $request->getQueryParam('email');
    $token = $request->getQueryParam('token');
    $stmt->bindParam(':email',$email);
    $stmt->bindParam(':token',$token);
    try{
        $stmt->execute();
        // $response = array('text' => 'success update');
        $response = array('response' => [ 'text' => 'success update', 'status' => 'success' ] );
        echo json_encode($response);
    }
    catch(PDOException $e){
        echo '{"response": {"message": '.$e->getMessage().'}';
    }
});

//user register
$app->post('/api/users/register', function (Request $request, Response $response) {
    $name = $request->getParam('name');
    $email = $request->getParam('email');
    $password = $request->getParam('password');
    $username = $request->getParam('username');
    $hashedPassword = password_hash($password,PASSWORD_BCRYPT);
	$token = 'qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
	$token = str_shuffle($token);
    $token = substr($token, 0, 32);

    $sql =  "INSERT INTO users (name,email,password,username,isEmailConfirmed,token)
             VALUES(:name,:email,:password,:username,'0',:token)";

    $mail = new PHPMailer(true);
    // $mail->SMTPDebug = 2;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'cariinplatform@gmail.com';
    $mail->Password = 'cariinapi123';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    //Recipients
    $mail->setFrom('cariinplatform@gmail.com', 'Register Bot [Cariin-App]');
    $mail->addAddress($email);
    //Attachments
    $mail->isHTML(true);
    $mail->Subject = 'Verified ur Account';
    $mail->Body    = '
        Please Click on Link Below:<br><br>
        <a href="https://cariinapp.000webhostapp.com/api/confirm_email.php?email='.$email.'&token='.$token.'">Click Here</a>';

    try{
        $db = new database();
        $db = $db->conn();
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':name',$name);
        $stmt->bindParam(':email',$email);
        $stmt->bindParam(':password',$hashedPassword);
        $stmt->bindParam(':username',$username);
        $stmt->bindParam(':token',$token);
        $stmt->execute();
        $mail->send();
        echo '{"response":{"message": "success register, please chech ur email", "status":"success"}';
    }
    catch(PDOException $e){
        echo '{"response": {"message": '.$e->getMessage().'}';
    }
});