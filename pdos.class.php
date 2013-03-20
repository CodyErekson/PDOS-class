<?php
//A class to create a PDO connection and pass it around in other classes as a singleton object

class pdos {

    private static $singleton;

    private $server   = ""; //database server
    private $user     = ""; //database login name
    private $pass     = ""; //database login password
    private $database = ""; //database name

    private $dbh;

    private function __construct($server=null, $user=null, $pass=null, $database=null) {
        if ( $server == null || $user == null || $pass == null || $database == null ) {
            throw new Exception("Connection information must be passed in when the object is first created.");
        }
        $this->server = $server;
        $this->user = $user;
        $this->pass = $pass;
        $this->database = $database;
        try {
            $this->dbh = new PDO("mysql:host=" . $this->server . ";dbname=" . $this->database, $this->user, $this->pass);
            $this->dbh->setAttribute (PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Could not connect to database: " . $e->getMessage());
        }
    }

    public static function obtain($server=null, $user=null, $pass=null, $database=null) {
        //get the PDO handle
        if ( !self::$singleton ) {
            self::$singleton = new pdos($server, $user, $pass, $database);
        }

        return self::$singleton->dbh;
    }

    public static function pass($server=null, $user=null, $pass=null, $database=null){
        //get this class itself
        if ( !self::$singleton ) {
            self::$singleton = new pdos($server, $user, $pass, $database);
        }

        return self::$singleton;
    }

    public function insert($table, $data){
        //perform an insertion query

        $columns = "";
        $values = "";

        foreach( $data as $key => $value ){
            $columns .= $key . ", ";
            $values .= ":" . $key . ", ";
        }
        $columns = rtrim($columns, ", ");
        $values = rtrim($values, ", ");

        $sth = $this->dbh->prepare("INSERT INTO " . $table . " (" . $columns . ") VALUES(" . $values . ")");
        foreach( $data as $key => $value ){
            if ( strpos($key, ".") !== false ){
                $sp = explode(".", $key);
                $p = $sp[1];
            } else {
                $p = $key;
            }
            $sth->bindValue(":" . $p, $value);
        }
        if ( !$sth->execute() ){
            return false;
        }
        $id = $this->dbh->lastInsertID();
        $sth->closeCursor();

        return $id;
    }

    public function update($table, $data, $where){
        //perform an update query; requires "where" array

        $set = "";
        foreach( $data as $key => $value ){
            $set .= $key . "=:" . $key . ", ";
        }
        $set = rtrim($set, ", ");

        $wh = "";
        foreach( $where as $key => $value ){
            if ( strpos($key, ".") !== false ){
                $sp = explode(".", $key);
                $p = $sp[1];
            } else {
                $p = $key;
            }
            $wh .= $key . "=:" . $p . " AND ";
        }
        $wh = rtrim($wh, " AND ");

        $sth = $this->dbh->prepare("UPDATE " . $table . " SET " . $set . " WHERE " . $wh);
        foreach( $data as $key => $value ){
            $sth->bindValue(":" . $key, $value);
        }
        foreach( $where as $key => $value ){
            if ( strpos($key, ".") !== false ){
                $sp = explode(".", $key);
                $p = $sp[1];
            } else {
                $p = $key;
            }
            $sth->bindValue(":" . $p, $value);
        }
        if ( !$sth->execute() ){
            return false;
        }
        $id = $this->dbh->lastInsertID();
        $sth->closeCursor();

        return $id;
    }

    public function query($table, $columns, $where=false, $like=false, $misc=false){
        //perform a query and return an associative array containing all rows
        //accepts either string or array for columns
        //like takes array and the % should be included where you want it in the value
        //$misc is for things like ORDER BY, LIMIT, GROUP BY, etc --> probably best to avoid using this
        //in these cases if there is a variable as it won't be passed through bindValue and therefore not
        //sanitized
        if ( is_array($columns) ){
            $cols = "";
            foreach( $columns as $col ){
                $cols .= $col . ", ";
            }
            $columns = rtrim($cols, ", ");
        }

        $wh = ""; //determine if we need "WHERE"

        if ( ($where) && ( is_array($where) ) ){
            $w = "";
            $wh = " WHERE ";
            foreach( $where as $key => $value ){
                if ( strpos($key, ".") !== false ){
                    $sp = explode(".", $key);
                    $p = $sp[1];
                } else {
                    $p = $key;
                }
                $w .= $key . "=:" . $p . " AND ";
            }
            $w = substr($w, 0, -5);
        } else {
            $w = "";
        }

        if ( ($like) && ( is_array($like) ) ){
            if ( strlen($w) > 0 ){
                $l = " AND ";
            } else {
                $l = "";
            }
            $wh = " WHERE ";
            foreach( $like as $key => $value ){
                if ( strpos($key, ".") !== false ){
                    $sp = explode(".", $key);
                    $p = $sp[1];
                } else {
                    $p = $key;
                }
                $l .= $key . " LIKE :" . $p . " AND ";
            }
            $l = substr($l, 0, -5);
        } else {
            $l = "";
        }

        $sth = $this->dbh->prepare("SELECT " . $columns . " FROM " . $table . $wh . $w . $l . " " . $misc);
        if ( ($where) && ( is_array($where) ) ){
            foreach( $where as $key => $value ){
                if ( strpos($key, ".") !== false ){
                    $sp = explode(".", $key);
                    $p = $sp[1];
                } else {
                    $p = $key;
                }
                $sth->bindValue(":" . $p, $value);
            }
        }
        if ( ($like) && ( is_array($like) ) ){
            foreach( $like as $key => $value ){
                if ( strpos($key, ".") !== false ){
                    $sp = explode(".", $key);
                    $p = $sp[1];
                } else {
                    $p = $key;
                }
                $sth->bindValue(":" . $p, $value);
            }
        }
        if ( !$sth->execute() ){
            return false;
        }
        $result = $sth->fetchAll(PDO::FETCH_ASSOC);
        $sth->closeCursor();
        $return = array();
        foreach($result as $row){
            $return[] = $row;
        }
        return $return;
    }

    public function select_single($table, $columns, $where=false, $like=false, $misc=false){
        //perform a query that will return only a single row, return false if no rows
        //accepts either string or array for columns
        //like takes array and the % should be included where you want it in the value
        if ( is_array($columns) ){
            $cols = "";
            foreach( $columns as $col ){
                $cols .= $col . ", ";
            }
            $columns = rtrim($cols, ", ");
        }

        $wh = ""; //determine if we need "WHERE"

        if ( ($where) && ( is_array($where) ) ){
            $w = "";
            $wh = " WHERE ";
            foreach( $where as $key => $value ){
                if ( strpos($key, ".") !== false ){
                    $sp = explode(".", $key);
                    $p = $sp[1];
                } else {
                    $p = $key;
                }
                $w .= $key . "=:" . $p . " AND ";
            }
            $w = substr($w, 0, -5);
        } else {
            $w = "";
        }

        if ( ($like) && ( is_array($like) ) ){
            if ( strlen($w) > 0 ){
                $l = " AND ";
            } else {
                $l = "";
            }
            $wh = " WHERE ";
            foreach( $like as $key => $value ){
                if ( strpos($key, ".") !== false ){
                    $sp = explode(".", $key);
                    $p = $sp[1];
                } else {
                    $p = $key;
                }
                $l .= $key . " LIKE :" . $p . " AND ";
            }
            $l = substr($l, 0, -5);
        } else {
            $l = "";
        }

        $sth = $this->dbh->prepare("SELECT " . $columns . " FROM " . $table . $wh . $w . $l . $misc);
        if ( ($where) && ( is_array($where) ) ){
            foreach( $where as $key => $value ){
                if ( strpos($key, ".") !== false ){
                    $sp = explode(".", $key);
                    $p = $sp[1];
                } else {
                    $p = $key;
                }
                $sth->bindValue(":" . $p, $value);
            }
        }
        if ( ($like) && ( is_array($like) ) ){
            foreach( $like as $key => $value ){
                if ( strpos($key, ".") !== false ){
                    $sp = explode(".", $key);
                    $p = $sp[1];
                } else {
                    $p = $key;
                }
                $sth->bindValue(":" . $p, $value);
            }
        }
        if ( !$sth->execute() ){
            return false;
        }
        $row = $sth->fetch(PDO::FETCH_ASSOC);
        $sth->closeCursor();
        if ( count($row) == 0 ){
            return false;
        }
        return $row;
    }

    public function delete($table, $where){
        //function to delete a column, where is an associative array
        if ( ($where) && ( is_array($where) ) ){
            $w = " WHERE ";
            foreach( $where as $key => $value ){
                if ( strpos($key, ".") !== false ){
                    $sp = explode(".", $key);
                    $p = $sp[1];
                } else {
                    $p = $key;
                }
                $w .= $key . "=:" . $p . " AND ";
            }
            $w = substr($w, 0, -5);
        } else {
            $w = "";
        }

        $sth = $this->dbh->prepare("DELETE FROM " . $table . $w);
        if ( ($where) && ( is_array($where) ) ){
            foreach( $where as $key => $value ){
                if ( strpos($key, ".") !== false ){
                    $sp = explode(".", $key);
                    $p = $sp[1];
                } else {
                    $p = $key;
                }
                $sth->bindValue(":" . $p, $value);
            }
        }
        if ( !$sth->execute() ){
            return false;
        }
        $sth->closeCursor();
        return true;
    }


}

?>
