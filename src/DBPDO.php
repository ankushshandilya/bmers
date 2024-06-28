<?php

namespace BMERS;

use \PDO;
use \stdClass;

class DBPDO {

    private $Host = null;
    private $Database = null;
    private $User = null;
    private $Password = null;
    public $Link_ID;   
    public $Query_ID;          
    public $Record; 
    public $Row;                
    public $Errno = 0;       
    public $Error = "";
    public $data = NULL;
    public $debug = false;
    public $table = NULL;
    public $sql = NULL;
    public $debugSQL = null;
    public $created_at = true;
    public $updated_at = true;
    private $cols;

    public function __construct($host, $db, $user, $pass) {
        $this->Host = $host;
        $this->Database = $db;
        $this->User = $user;
        $this->Password = $pass;

        $this->table = $this->table == NULL ? strtolower(get_class($this)) . 's' : $this->table;
        $this->data = new stdClass;
        return $this;
    }

    public function connect() {
        if (false == $this->Link_ID)
            $this->Link_ID = new PDO("mysql:host={$this->Host};dbname={$this->Database};charset=utf8mb4", $this->User, $this->Password);
        if (!$this->Link_ID)
            $this->halt("Link-ID == false, connect failed");
        if (!$this->Link_ID->query(sprintf("use %s", $this->Database)))
            $this->halt("cannot use database " . $this->Database);
    }

    public function query($queryString, $params = []) {
        $this->connect();
        $statement = $this->Link_ID->prepare($queryString);
        $this->Query_ID = $statement->execute($params) ? $statement : false;

        $this->Row = 0;
        $this->Errno = $this->Link_ID->errorInfo()[1];
        $this->Error = $this->Link_ID->errorInfo()[2];
        if (!$this->Query_ID)
            $this->halt("Invalid SQL: " . $queryString);
        return $this->Query_ID;
    }
    
    public function run() {
        return $this->query($this->sql);
    }    

    public function halt($msg) {
        if (ENVIRONMENT):
            header('location:/s/error');
        else:
            printf("</td></tr></table><b>Database error:</b> %s<br>n", $msg);
            printf("<b>MySQL Error</b>: %s (%s)<br>n", $this->Errno, $this->Error);
            die("Session halted.");
        endif;
    }

    public function next() {
        $this->Record = $this->Query_ID->fetch(PDO::FETCH_OBJ);
        $this->Row += 1;
        $this->Errno = $this->Link_ID->errorInfo()[1];
        $this->Error = $this->Link_ID->errorInfo()[2];
        $stat = is_object($this->Record);
        if (!$stat) {
            $this->Query_ID->closeCursor();
            $this->Query_ID = null;
        }
        return $stat;
    }

    public function single() {
        $this->Record = $this->Query_ID->fetch(PDO::FETCH_OBJ);
        return $this;
    }

    public function count() {
        return $this->Query_ID->rowCount();
    }

    public function truncate() {
        $this->sql = "TRUNCATE {$this->table}";
        return $this->query($this->sql);
    }
    
    public function drop($id, $col = NULL) {
        $this->connect();

        if($col == NULL):
            $this->sql = "DELETE FROM {$this->table} WHERE id = :id";
            $statement = $this->Link_ID->prepare($this->sql);
            $statement->bindParam(':id', $id, PDO::PARAM_INT);
        else:
            $this->sql = "DELETE FROM {$this->table} WHERE $col = :id";
            $statement = $this->Link_ID->prepare($this->sql);
            $statement->bindParam(':id', $id, PDO::PARAM_STR);
        endif;
        
        return $statement->execute() ? $this : false;
    }

    public function create() {
        if ($this->created_at) $this->data->created_at = date('Y-m-d H:i:s');
        if ($this->updated_at) $this->data->updated_at = date('Y-m-d H:i:s');
        $array = get_object_vars($this->data);
        $this->connect();
        $this->sql = "INSERT INTO {$this->table} (" . implode(',', array_keys($array)) . " )
        VALUES ( :" . implode(" , :", array_keys($array)) . ")";

        if ($this->debug):
            $this->debugSQL =  "INSERT INTO {$this->table} (" . implode(',', array_keys($array)) . " )
            VALUES ( '" . implode("' ,'", array_values($array)) . "')";
        endif;

        $statement = $this->Link_ID->prepare($this->sql);
        return $statement->execute($array) ? $this : false;
    }
    
    public function createIgnore() {
        if ($this->created_at) $this->data->created_at = date('Y-m-d H:i:s');
        if ($this->updated_at) $this->data->updated_at = date('Y-m-d H:i:s');
        $array = get_object_vars($this->data);
        $this->connect();
        $this->sql = "INSERT IGNORE INTO {$this->table} (" . implode(',', array_keys($array)) . " )
        VALUES ( :" . implode(" , :", array_keys($array)) . ")";

        if ($this->debug):
            $this->debugSQL =  "INSERT IGNORE INTO {$this->table} (" . implode(',', array_keys($array)) . " )
            VALUES ( '" . implode("' ,'", array_values($array)) . "')";
        endif;

        $statement = $this->Link_ID->prepare($this->sql);
        return $statement->execute($array) ? $this : false;
    }    

    public function insertID() {
        return $this->Link_ID->lastInsertId();
    }

    public function toggle($col, $id) {
        $this->sql = "UPDATE {$this->table} SET $col = NOT $col WHERE id = :id";
        $statement = $this->Link_ID->prepare($this->sql);
        $statement->bindParam(':id', $id, PDO::PARAM_INT);
        return $statement->execute() ? $this : false;
    }    

    public function update($id, $col = NULL) {
        if ($this->updated_at)
            $this->data->updated_at = date('Y-m-d H:i:s');
        $this->connect();
        $temp = "";
        $bind = [];
        
        foreach ($this->data as $k => $v):
            $temp .= $k . " = :" . $k . ", ";
            $bind[":$k"] = $v;
        endforeach;

        if(is_array($id)):
            $where = "WHERE ";
            foreach($id as $k => $v):
                if($where === "WHERE "):
                    $where .= "$k = :$k";
                else:
                    $where .= " AND $k = :$k";
                endif;
                $bind[":$k"] = $v;
            endforeach;

            $this->sql = "UPDATE {$this->table} SET " . rtrim($temp, ", ") . " $where";
        else:
            $bind[":id"] = $id;
            $this->sql = "UPDATE {$this->table} SET " . rtrim($temp, ", ") . " WHERE " . ($col == NULL ? 'id' : $col) . " = :id";
        endif;

        $statement = $this->Link_ID->prepare($this->sql);
        return $statement->execute($bind);
    }

    public function one($id, $col = NULL) {
        if (isset($this->cols)):
            $this->sql = $col == NULL ? "SELECT {$this->cols} FROM {$this->table} WHERE id = :id" : "SELECT {$this->cols} FROM {$this->table} WHERE $col = :id";
        else:
            $this->sql = $col == NULL ? "SELECT * FROM {$this->table} WHERE id = :id" : "SELECT * FROM {$this->table} WHERE $col = :id";
        endif;

        $statement = $this->Link_ID->prepare($this->sql);
        $statement->bindParam(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $this->Query_ID = $statement;
        $this->single();
        return $this;
    }

    public function all() {
        $this->sql = "SELECT * FROM {$this->table}";
        return $this->query($this->sql);
    }

    public function get(array $args) {
        $bind = [];
        if (isset($this->cols)):
            $this->sql = "SELECT {$this->cols} FROM {$this->table}";
        else:
            $this->sql = "SELECT * FROM {$this->table}";
        endif;
        foreach ($args as $k => $v):
            $this->sql .= " $k $v";
            if (strtolower($k) === 'where'):
                $bind[":$v"] = $v;
            endif;
        endforeach;

        $statement = $this->Link_ID->prepare($this->sql);
        return $statement->execute($bind) ? $this : false;
    }

    public function getArray() {
        $this->connect();
        return $this->Query_ID->fetchAll(PDO::FETCH_OBJ);
    }

    public function table($table) {
        $this->table = $table;
        return $this;
    }

    public function toJson($array = false) {
        if ($array)
            return json_encode($array);
        else
            return json_encode($this->Query_ID->fetchAll(PDO::FETCH_OBJ));
    }

    public function cols($cols) {
        $this->cols = $cols;
        return $this;
    }

    public function lastSQL() {
        return $this->debugSQL != null ? $this->debugSQL : $this->sql;
    }

}
