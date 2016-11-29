<?php 
namespace BMERS;
class DB {
    private $Host     = DB_HOST; 
    private $Database = DB_NAME; 
    private $User     = DB_USER; 
    private $Password = DB_PASS; 

    public $Link_ID  = false;   // Result of mysql_connect(). 
    public $Query_ID ;          // Result of most recent mysql_query(). 
    public $Record   = array(); // current mysql_fetch_array()-result. 
    public $Row;                // current row number. 

    public $Errno    = 0;       // error state of query... 
    public $Error    = ""; 
    public $data = NULL;
    
    protected $debug = false;
    protected $table = NULL;
    protected $sql = NULL;
    protected $created_at = true;
    protected $updated_at = true;

    public function __construct($host, $db, $user, $pass){
        $this->Host     = $host; 
        $this->Database = $db; 
        $this->User     = $user; 
        $this->Password = $pass; 
    
        $this->table = $this->table == NULL ? strtolower(get_class($this)).'s' : $this->table;
        $this->data = new stdClass;
        return $this;
    }
    
    public function connect(){ 
        if( false == $this->Link_ID ) $this->Link_ID = new PDO("mysql:host={$this->Host};dbname={$this->Database};charset=utf8", $this->User, $this->Password);
        if( !$this->Link_ID ) $this->halt( "Link-ID == false, connect failed" ); 
        if( !$this->Link_ID->query(sprintf( "use %s", $this->Database) )) $this->halt( "cannot use database ".$this->Database ); 
    } 
			
    public function query( $Query_String ) { 
        $this->connect(); 
        $this->Query_ID = $this->Link_ID->query( $Query_String ); 

        $this->Row = 0; 
        $this->Errno = $this->Link_ID->errorInfo()[1]; 
        $this->Error = $this->Link_ID->errorInfo()[2]; 
        if( !$this->Query_ID ) $this->halt( "Invalid SQL: ".$Query_String ); 
        return $this->Query_ID; 
	} 

    public function halt( $msg ) {
   		if(ENVIRONMENT): 
			header('location:/s/error'); 
		else:
			printf( "</td></tr></table><b>Database error:</b> %s<br>n", $msg ); 
			printf( "<b>MySQL Error</b>: %s (%s)<br>n", $this->Errno, $this->Error ); 
			die( "Session halted." ); 
		endif;
	} 

    public function next() { 
        $this->Record = $this->Query_ID->fetch(PDO::FETCH_OBJ);
        $this->Row += 1; 
        $this->Errno = $this->Link_ID->errorInfo()[1];  
        $this->Error = $this->Link_ID->errorInfo()[2]; 
        $stat = is_object( $this->Record ); 
        if( !$stat ) { 
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
	
	public	function drop($id){
		$this->connect(); 
		$this->sql = "DELETE FROM {$this->table} WHERE id = :id";
		$statement = $this->Link_ID->prepare($this->sql);
		$statement->bindParam(':id', $id, PDO::PARAM_INT);
		$statement->execute();
        return $this;
	}

	public function create(){
        if($this->created_at) $this->data->created_at = date('Y-m-d H:i:s');
        $array = get_object_vars($this->data);
		$this->connect(); 				
		$this->sql = "INSERT INTO {$this->table} (" . implode(',',array_keys($array)) ." )
		VALUES ( :".implode(" , :",array_keys($array)). ")";
        
        if($this->debug):
            echo  "INSERT INTO {$this->table} (" . implode(',',array_keys($array)) ." )
            VALUES ( '".implode("' ,'",  array_values($array)). "')";
        endif;
        
		$statement = $this->Link_ID->prepare($this->sql);
		return $statement->execute($array) ? $this : false;
	}

    public function insertID(){
        return $this->Link_ID->lastInsertId();
    }
    
	public function update($id, $col = NULL){
        if($this->updated_at) $this->data->updated_at = date('Y-m-d H:i:s');
		$this->connect(); 		
		$temp = "";
        if($this->debug):
            foreach($this->data as $k=>$v):
            $temp .= $k . " = '" . $v . "', ";
            endforeach;		
            echo "UPDATE {$this->table} SET ". rtrim($temp, ", ") ." WHERE ".($col == NULL ? 'id' : $col) ." = $id";
        else:
            foreach($this->data as $k=>$v):
            $temp .= $k . " = :" . $k . ", ";
            $bind[":$k"] = $v;
            endforeach;		
            $bind[":id"] = $id;
            $this->sql = "UPDATE {$this->table} SET ". rtrim($temp, ", ") ." WHERE ".($col == NULL ? 'id' : $col) ." = :id";
            return $this->Link_ID->prepare($this->sql)->execute($bind);
        endif;
	 }

	 public function one($id) {
           $this->sql = "SELECT * FROM {$this->table} WHERE id=$id";
           $this->query($this->sql);
           $this->single();
           return $this;
         }
    
	public function all(){
        $this->sql = "SELECT * FROM {$this->table}";
        $this->query($this->sql);
        return $this;
	}
    
	public function get($start = 0, $length = 0){
		$this->sql = !$length ? "{$this->sql} LIMIT $start" : "{$this->sql} LIMIT $start, $length";
        $this->query($this->sql);
        return $this;
	}

	public function take($clause = NULL){
        $this->sql = "SELECT * FROM {$this->table} $clause";
        $this->query($this->sql);
        return $this;
	}
    
    public function takeCols($cols = []){
        if(count($cols)):
            $this->sql = "SELECT ".implode(',',$cols)." FROM {$this->table}";
            $this->query($this->sql);
            return $this;
        else:
            return false;
        endif;
	}

	public function set($table){
        $this->table = $table."s";
        return $this;
	}

    public function dump($pr = false){
        echo '<pre>';
        $pr ? print_r($this) : var_dump($this);
        echo '<pre>';
    }
}