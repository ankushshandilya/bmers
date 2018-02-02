<?php

namespace BMERS;

use \PDO;
use \stdClass;

class DB {

    private $Host = null;
    private $Database = null;
    private $User = null;
    private $Password = null;
    public $Link_ID = false;   // Result of mysql_connect(). 
    public $Query_ID;          // Result of most recent mysql_query(). 
    public $Record = array(); // current mysql_fetch_array()-result. 
    public $Row;                // current row number. 
    public $Errno = 0;       // error state of query... 
    public $Error = "";
    public $data = NULL;
    public $debug = false;
    public $table = NULL;
    public $sql = NULL;
    public $debugSQL = null;
    public $created_at = true;
    public $updated_at = true;

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
            $this->Link_ID = new PDO("mysql:host={$this->Host};dbname={$this->Database};charset=utf8", $this->User, $this->Password);
        if (!$this->Link_ID)
            $this->halt("Link-ID == false, connect failed");
        if (!$this->Link_ID->query(sprintf("use %s", $this->Database)))
            $this->halt("cannot use database " . $this->Database);
    }

    public function query($Query_String) {
        $this->connect();
        $this->Query_ID = $this->Link_ID->query($Query_String);

        $this->Row = 0;
        $this->Errno = $this->Link_ID->errorInfo()[1];
        $this->Error = $this->Link_ID->errorInfo()[2];
        if (!$this->Query_ID)
            $this->halt("Invalid SQL: " . $Query_String);
        return $this->Query_ID;
    }
    
    //RUN AND QUERY IN FACT IS THE SAME BUT RUN HAS NO PARAM
    //RUN ASSUME $this->sql has value
    public function run() {
        $this->connect();
        $this->Query_ID = $this->Link_ID->query($this->sql);

        $this->Row = 0;
        $this->Errno = $this->Link_ID->errorInfo()[1];
        $this->Error = $this->Link_ID->errorInfo()[2];
        if (!$this->Query_ID)
            $this->halt("Invalid SQL: " . $this->sql);
        return $this->Query_ID;
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
        $this->connect();
        $this->sql = "TRUNCATE {$this->table} ";
        $this->query($this->sql);
        return $this;    }
    
    public function drop($id) {
        $this->connect();
        $this->sql = "DELETE FROM {$this->table} WHERE id = :id";
        $statement = $this->Link_ID->prepare($this->sql);
        $statement->bindParam(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        return $this;
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

    public function insertID() {
        return $this->Link_ID->lastInsertId();
    }

    public function update($id, $col = NULL) {
        if ($this->updated_at)
            $this->data->updated_at = date('Y-m-d H:i:s');
        $this->connect();
        $temp = "";
        if ($this->debug):
            foreach ($this->data as $k => $v):
                if ($v == NULL):
                    $temp .= $k . " = " . NULL . ", ";
                else:
                    $temp .= $k . " = '" . $v . "', ";
                endif;
            endforeach;
            $this->debugSQL =  "UPDATE {$this->table} SET " . rtrim($temp, ", ") . " WHERE " . ($col == NULL ? 'id' : $col) . " = $id";
        else:
            //MORE THAN ONE CONDITIONS IN WHERE CLAUSE
            if(is_array($id)):
                foreach ($this->data as $k => $v):
                    $temp .= $k . " = :" . $k . ", ";
                    $bind[":$k"] = $v;
                endforeach;
                
                $where = "WHERE ";
                foreach($id as $k => $v):
                    if($where === "WHERE "):
                        $where .= "$k = '$v'";
                    else:
                        $where .= "AND $k = '$v'";
                    endif;
                endforeach;
                
                $this->sql = "UPDATE {$this->table} SET " . rtrim($temp, ", ") . " $where";
                
            else:
                foreach ($this->data as $k => $v):
                    $temp .= $k . " = :" . $k . ", ";
                    $bind[":$k"] = $v;
                endforeach;
                $bind[":id"] = $id;
                $this->sql = "UPDATE {$this->table} SET " . rtrim($temp, ", ") . " WHERE " . ($col == NULL ? 'id' : $col) . " = :id";
            endif;
            return $this->Link_ID->prepare($this->sql)->execute($bind);
        endif;
    }

    public function one($id) {
        $this->sql = "SELECT * FROM {$this->table} WHERE id=$id";
        $this->query($this->sql);
        $this->single();
        return $this;
    }

    public function all() {
        $this->sql = "SELECT * FROM {$this->table}";
        $this->query($this->sql);
        return $this;
    }

    public function get($start = 0, $length = 0) {
        $this->sql = !$length ? "{$this->sql} LIMIT $start" : "{$this->sql} LIMIT $start, $length";
        $this->query($this->sql);
        return $this;
    }

    public function take($clause = NULL) {
        $this->sql = "SELECT * FROM {$this->table} $clause";
        $this->query($this->sql);
        return $this;
    }

    public function takeCols($cols = [], $clause = NULL) {
        if (count($cols)):
            $this->sql = "SELECT " . implode(',', $cols) . " FROM {$this->table} $clause";
            $this->query($this->sql);
            return $this;
        else:
            return false;
        endif;
    }

    public function set($table) {
        $this->table = $table;
        return $this;
    }
    
    public function select($type = null){
        $this->sql = "SELECT $type ";
        return $this;
    }
    public function fields($fields = []){
        $this->sql .= implode(', ', $fields);
        return $this;
    }
    
    public function from($table){
        $this->sql .= " FROM $table ";
        return $this;
    }
    
    public function on($left, $right, $clause = null){
        $this->sql .= " ON $left = $right $clause";
        return $this;
    }
    
    public function join($table){
        $this->sql .= " JOIN $table ";
        return $this;
    }
    public function leftJoin($table){
        $this->sql .= " LEFT JOIN $table ";
        return $this;
    }
    
    public function rightJoin($table){
        $this->sql .= " RIGHT JOIN $table ";
        return $this;
    }
    
    public function where($where){
        $this->sql .= " WHERE $where ";
        return $this;
    }    

    public function aand($and){
        $this->sql .= " AND $and ";
        return $this;
    }    
    
    public function groupby($field){
        $this->sql .= " GROUP BY $field ";
        return $this;
    }    
    
    public function clause($clause){
        $this->sql .= " $clause ";
        return $this;
    }
    
    public function dump($pr = false) {
        echo '<pre>';
        $pr ? print_r($this) : var_dump($this);
        echo '<pre>';
    }
    
    /*********************************
     * INTRODUCING BLOB FUNCTIONALITY
     **********************************/
    
    /**
     * insert blob into the files table
     * @param string $filePath
     * @param string $mime mimetype
     * @return bool
     */
    public function insertBlob($filePath, $mime, $blobColName) {
        $blob = fopen($filePath, 'rb');
        
        $this->connect();
        $this->sql = "INSERT INTO {$this->table} (mime, $blobColName)
        VALUES(:mime,:$blobColName)";

        $statement = $this->Link_ID->prepare($this->sql);
        
        $statement->bindParam(":mime", $mime);
        $statement->bindParam(":$blobColName", $blob, PDO::PARAM_LOB);
        return $statement->execute() ? $this : false;      
    }  
    
    public function insertBlobFromURL($filePath, $mime, $blobColName) {
        $ch = curl_init();
        // set URL and other appropriate options
        $theurl = $filePath;
        curl_setopt($ch, CURLOPT_URL, $theurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($ch, CURLOPT_HEADER, 0);
        // grab URL and pass it to the browser
        $blob = file_get_contents($theurl);;//curl_exec($ch);
        // close cURL resource, and free up system resources
        curl_close($ch);        

        $this->connect();
        $this->sql = "INSERT INTO {$this->table} (mime, $blobColName)
        VALUES(:mime,:$blobColName)";

        $statement = $this->Link_ID->prepare($this->sql);
        
        $statement->bindParam(":mime", $mime);
        $statement->bindParam(":$blobColName", $blob, PDO::PARAM_LOB);
        return $statement->execute() ? $this : false;      
    }       
        

    /**
     * update the files table with the new blob from the file specified
     * by the filepath
     * @param int $id
     * @param string $filePath
     * @param string $mime
     * @return bool
     */
    function updateBlob($id, $filePath, $mime, $blobColName) {
 
        $blob = fopen($filePath, 'rb');
 
        $this->sql = "UPDATE {$this->table}
                SET mime = :mime,
                    $blobColName = :$blobColName
                WHERE id = :id;";
        $this->connect(); 
        $statement = $this->Link_ID->prepare($this->sql);
 
        $statement->bindParam(":mime", $mime);
        $statement->bindParam(":$blobColName", $blob, PDO::PARAM_LOB);
        $statement->bindParam(":id", $id);
        return $statement->execute();
    }
    
    function updateBlobFromURL($id, $filePath, $mime, $blobColName) {
 
        $ch = curl_init();
        // set URL and other appropriate options
        $theurl = $filePath;
        curl_setopt($ch, CURLOPT_URL, $theurl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt($ch, CURLOPT_HEADER, 0);
        // grab URL and pass it to the browser
        $blob = file_get_contents($theurl);;//curl_exec($ch);
        // close cURL resource, and free up system resources
        curl_close($ch);          
        
        $this->connect();
        $this->sql = "UPDATE {$this->table}
                SET mime = :mime,
                    $blobColName = :$blobColName
                WHERE id = :id;";
 
        $statement = $this->Link_ID->prepare($this->sql);
 
        $statement->bindParam(":mime", $mime);
        $statement->bindParam(":$blobColName", $blob, PDO::PARAM_LOB);
        $statement->bindParam(":id", $id);
        return $statement->execute();
    }    
    /**
     * select data from the the files
     * @param int $id
     * @return array contains mime type and BLOB data
     */
    public function selectBlob($id, $blobColName) {
        $this->connect();  
        $this->sql = "SELECT mime, $blobColName FROM {$this->table}  WHERE id = $id";
        $this->query($this->sql);
        return $this;
    }

    
    
}
