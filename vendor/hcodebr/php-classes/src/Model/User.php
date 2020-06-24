<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use Hcode\Mailer;
use \Hcode\Model;

class User extends Model{

    const SESSION = "User";
    const SESSION_ERROR = 'user';
    const REGISTER_ERROR = "user";
    const SECRET = "HcodePhp7_Secret";

    public static function getFromSession(){

        $user = new User();
        
        if (isset($_SESSION[User::SESSION]) && $_SESSION[User::SESSION]["iduser"] > 0){

            $user->setData($_SESSION[User::SESSION]);

        }

        return $user;
    }
    
    public static function checkLogin($inadmin = true){

        if(!isset($_SESSION[User::SESSION]) || !$_SESSION[User::SESSION] || !(int)$_SESSION[User::SESSION]["iduser"] > 0){

            return false;

        } else {

            if ($inadmin === true && (bool)$_SESSION[User::SESSION]["inadmin"] === true) {

                return true;

            } else if ($inadmin === false) {

                return true;

            } else {

                return false;

            }

        }

    }

    public static function login($login, $password){

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING (idperson) WHERE deslogin = :LOGIN", [
            ":LOGIN" => $login
        ]);

        if (count($results) === 0){

            throw new \Exception("Usuário inexistente ou senha inválida");
            
        }

        $data = $results[0];

        if (password_verify($password, $data["despassword"]) === true) {

            $user = new User();

            $user->setData($data);

            $_SESSION[User::SESSION] = $user->getValues();
            
            return $user;

        } else {

            throw new \Exception("Usuário inexistente ou senha inválida");

        }

    }

    public static function verifyLogin($inadmin = true){
 
        if (!User::checkLogin($inadmin)) {
            if ($inadmin) {

                header("Location: /admin/login");

            } else {
                
                header("Location: /login");

            }
            exit;
        }

    }

    public static function logout(){

        $_SESSION[User::SESSION] = null;

    }

    public static function listAll(){

        $sql = new Sql();

        return $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY b.desperson");

    }

    public function save(){

        $sql = new Sql();

        $result = $sql->select("CALL sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", [
            ":desperson" => $this->getdesperson(),
            ":deslogin" => $this->getdeslogin(),
            ":despassword" => User::getPasswordHash($this->getdespassword()),
            ":desemail" => $this->getdesemail(),
            ":nrphone" => $this->getnrphone(),
            ":inadmin" => $this->getinadmin()
        ]);

        $this->setData($result[0]);

    }

    public function get($iduser){

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.iduser = :iduser", [
            ":iduser" => $iduser
        ]);

        $this->setData($results[0]);
        
    }

    public function update(){

        $sql = new Sql();

        $reuslt = $sql->select("CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", [
            ":iduser" => $this->getiduser(),
            ":desperson" => $this->getdesperson(),
            ":deslogin" => $this->getdeslogin(),
            ":despassword" => User::getPasswordHash($this->getdespassword()),
            ":desemail" => $this->getdesemail(),
            ":nrphone" => $this->getnrphone(),
            ":inadmin" => $this->getinadmin()
        ]);

        $this->setData($reuslt[0]);

    }

    public function delete(){

        $sql = new Sql();

        $sql->query("CALL sp_users_delete(:iduser)",[
            ":iduser" => $this->getiduser()
        ]);

    }

    public static function getForgot($email, $inadmin = true){

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_persons a INNER JOIN tb_users b USING(idperson) WHERE a.desemail = :email", [
            ":email" => $email
        ]);

        if (count($results) === 0) {

            throw new \Exception("Não foi possível recuperar a senha.");
        
        } else {

            $data = $results[0];

            $results2 = $sql->select("CALL sp_userspasswordsrecoveries_create(:iduser, :desip)", [
                ":iduser" => $data['iduser'],
                ":desip" => $_SERVER["REMOTE_ADDR"]
            ]);

            if (count($results2) === 0) {

                throw new \Exception("Não foi possível recuperar a senha.");

            } else {

                $dataRecovery = $results2[0];

                $code = base64_encode(md5($dataRecovery['idrecovery']));
                
                if($inadmin === true){

                    $link = "http://www.hcodecommerce.com.br/admin/forgot/reset?code=$code";

                } else {

                    $link = "http://www.hcodecommerce.com.br/forgot/reset?code=$code";

                }

                $mailer = new Mailer($data["desemail"], $data["desperson"], "Redefiir senha da Hcode Store", "forgot", [
                    "name" => $data["desperson"],
                    "link" => $link
                ]);

                $mailer->send();

                return $data;

            }

        }
    }

    public static function validForgotDecrypt($code){

        $sql = new Sql();

        $idrecovery = base64_decode($code);

        $results = $sql->select("SELECT * FROM tb_userspasswordsrecoveries a INNER JOIN tb_users b USING(iduser) INNER JOIN tb_persons c USING(idperson) WHERE MD5(a.idrecovery) = :idrecovery AND a.dtrecovery IS NULL AND DATE_ADD(a.dtregister, INTERVAL 1 HOUR) >= NOW()", [
            "idrecovery" => $idrecovery
        ]);

        if (count($results) === 0) {
            
            throw new \Exception("Não foi possível recuperar a senha.");
            
        } else {

            return $results[0];

        }

    }

    public static function setForgotUsed($idrecovery){

        $sql = new Sql();

        $sql->query("UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery", [
            ":idrecovery" => $idrecovery
        ]);

    }

    public function setPassword($password){

        $sql = new Sql();

        $sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser", [
            ":password" => User::getPasswordHash($password),
            ":iduser" => $this->getiduser()
        ]);

    }

    public static function setMsgError($msg){

        $_SESSION[User::SESSION_ERROR] = $msg;

    }

    public static function getMsgError(){

        $msg = (isset($_SESSION[User::SESSION_ERROR])) ? $_SESSION[User::SESSION_ERROR] : "";

        User::clearMsgError();

        return $msg;

    }

    public static function clearMsgError(){

        $_SESSION[User::SESSION_ERROR] = "";

    }

    public static function getPasswordHash($password){
        
        return password_hash($_POST["password"], PASSWORD_DEFAULT, [
            "cost" => 12
        ]);

    }

    public static function setRegisterError($msg){

        $_SESSION[User::REGISTER_ERROR] = $msg;

    }

    public static function getRegisterError(){

        $msg = (isset($_SESSION[User::REGISTER_ERROR])) ? $_SESSION[User::REGISTER_ERROR] : "";

        User::clearRegisterError();

        return $msg;

    }

    public static function clearRegisterError(){

        $_SESSION[User::REGISTER_ERROR] = "";

    }

    public static function checkLoginExist($login){

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :deslogin", [
            ":deslogin" => $login
        ]);

        return (count($results) > 0);

    }

}

?>