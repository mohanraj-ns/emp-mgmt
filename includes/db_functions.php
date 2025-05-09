<?php
/**
 * Database functions
 * Contains database connection and query functions with Redis caching
 */

// Database connection
$db_connection = null;
$redis = null;

/**
 * Get database connection (creates new if doesn't exist)
 * 
 * @return mysqli Database connection
 */
function get_db_connection() {
    global $db_connection;
    
    // If connection already exists, return it
    if ($db_connection !== null && $db_connection->ping()) {
        return $db_connection;
    }
    
    // Create new connection
    $db_connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check for connection errors
    if ($db_connection->connect_error) {
        error_log("Database connection failed: " . $db_connection->connect_error);
        die("Database connection failed. Please try again later.");
    }
    
    // Set charset
    $db_connection->set_charset(DB_CHARSET);
    
    return $db_connection;
}

/**
 * Get Redis connection (creates new if doesn't exist)
 * 
 * @return Redis|null Redis connection or null if not available
 */
function get_redis_connection() {
    global $redis;
    
    // If Redis is not enabled or extension not loaded, return null
    if (!CACHE_ENABLED || !extension_loaded('redis')) {
        return null;
    }
    
    // If connection already exists, return it
    if ($redis !== null) {
        try {
            $redis->ping();
            return $redis;
        } catch (Exception $e) {
            // Connection failed, will create new one
        }
    }
    
    // Create new connection
    try {
        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT);
        
        // Authenticate if password is set
        if (REDIS_PASSWORD !== null) {
            $redis->auth(REDIS_PASSWORD);
        }
        
        return $redis;
    } catch (Exception $e) {
        error_log("Redis connection failed: " . $e->getMessage());
        return null;
    }
}

/**
 * Prepare and execute a SQL query
 * 
 * @param string $query SQL query with placeholders
 * @param string $types Types of parameters (i:int, d:double, s:string, b:blob)
 * @param array $params Parameters to bind
 * @return mysqli_stmt|false Prepared statement or false on error
 */
function db_prepare_execute($query, $types = "", $params = []) {
    $conn = get_db_connection();
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Query preparation failed: " . $conn->error . " (Query: $query)");
        return false;
    }
    
    // Bind parameters if any
    if (!empty($params)) {
        // Create array with types as first element, followed by params
        $bind_params = array_merge([$types], $params);
        
        // Get reference to each array element for bind_param
        $refs = [];
        foreach ($bind_params as $key => $value) {
            $refs[$key] = &$bind_params[$key];
        }
        
        // Call bind_param with references
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    
    // Execute the query
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error . " (Query: $query)");
        return false;
    }
    
    return $stmt;
}

/**
 * Execute a SELECT query and fetch all results
 * 
 * @param string $query SQL query with placeholders
 * @param string $types Types of parameters (i:int, d:double, s:string, b:blob)
 * @param array $params Parameters to bind
 * @param string $cache_key Cache key (if caching is enabled)
 * @param int $cache_ttl Cache TTL in seconds (if caching is enabled)
 * @return array|null Array of results or null on error
 */
function db_fetch_all($query, $types = "", $params = [], $cache_key = null, $cache_ttl = CACHE_TTL) {
    // Try to get from cache first if caching is enabled and key provided
    if (CACHE_ENABLED && $cache_key !== null) {
        $redis = get_redis_connection();
        if ($redis !== null) {
            $cached_data = $redis->get($cache_key);
            if ($cached_data !== false) {
                return json_decode($cached_data, true);
            }
        }
    }
    
    $stmt = db_prepare_execute($query, $types, $params);
    if (!$stmt) {
        return null;
    }
    
    $result = $stmt->get_result();
    $data = [];
    
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    $stmt->close();
    
    // Store in cache if caching is enabled and key provided
    if (CACHE_ENABLED && $cache_key !== null && !empty($data)) {
        $redis = get_redis_connection();
        if ($redis !== null) {
            $redis->setex($cache_key, $cache_ttl, json_encode($data));
        }
    }
    
    return $data;
}

/**
 * Execute a SELECT query and fetch single row
 * 
 * @param string $query SQL query with placeholders
 * @param string $types Types of parameters (i:int, d:double, s:string, b:blob)
 * @param array $params Parameters to bind
 * @param string $cache_key Cache key (if caching is enabled)
 * @param int $cache_ttl Cache TTL in seconds (if caching is enabled)
 * @return array|null Array with row data or null if not found/error
 */
function db_fetch_row($query, $types = "", $params = [], $cache_key = null, $cache_ttl = CACHE_TTL) {
    // Try to get from cache first if caching is enabled and key provided
    if (CACHE_ENABLED && $cache_key !== null) {
        $redis = get_redis_connection();
        if ($redis !== null) {
            $cached_data = $redis->get($cache_key);
            if ($cached_data !== false) {
                return json_decode($cached_data, true);
            }
        }
    }
    
    $stmt = db_prepare_execute($query, $types, $params);
    if (!$stmt) {
        return null;
    }
    
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $stmt->close();
    
    // Store in cache if caching is enabled and key provided
    if (CACHE_ENABLED && $cache_key !== null && $row !== null) {
        $redis = get_redis_connection();
        if ($redis !== null) {
            $redis->setex($cache_key, $cache_ttl, json_encode($row));
        }
    }
    
    return $row;
}

/**
 * Execute an INSERT query
 * 
 * @param string $query SQL query with placeholders
 * @param string $types Types of parameters (i:int, d:double, s:string, b:blob)
 * @param array $params Parameters to bind
 * @param array $cache_keys Cache keys to invalidate after insert
 * @return int|false Last insert ID or false on error
 */
function db_insert($query, $types = "", $params = [], $cache_keys = []) {
    $stmt = db_prepare_execute($query, $types, $params);
    if (!$stmt) {
        return false;
    }
    
    $insert_id = $stmt->insert_id;
    $stmt->close();
    
    // Invalidate cache keys if any
    invalidate_cache_keys($cache_keys);
    
    return $insert_id;
}

/**
 * Execute an UPDATE query
 * 
 * @param string $query SQL query with placeholders
 * @param string $types Types of parameters (i:int, d:double, s:string, b:blob)
 * @param array $params Parameters to bind
 * @param array $cache_keys Cache keys to invalidate after update
 * @return int|false Number of affected rows or false on error
 */
function db_update($query, $types = "", $params = [], $cache_keys = []) {
    $stmt = db_prepare_execute($query, $types, $params);
    if (!$stmt) {
        return false;
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    // Invalidate cache keys if any
    invalidate_cache_keys($cache_keys);
    
    return $affected_rows;
}

/**
 * Execute a DELETE query
 * 
 * @param string $query SQL query with placeholders
 * @param string $types Types of parameters (i:int, d:double, s:string, b:blob)
 * @param array $params Parameters to bind
 * @param array $cache_keys Cache keys to invalidate after delete
 * @return int|false Number of affected rows or false on error
 */
function db_delete($query, $types = "", $params = [], $cache_keys = []) {
    $stmt = db_prepare_execute($query, $types, $params);
    if (!$stmt) {
        return false;
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    // Invalidate cache keys if any
    invalidate_cache_keys($cache_keys);
    
    return $affected_rows;
}

/**
 * Get number of rows that would be returned by a SELECT query
 * 
 * @param string $query SQL query with placeholders
 * @param string $types Types of parameters (i:int, d:double, s:string, b:blob)
 * @param array $params Parameters to bind
 * @param string $cache_key Cache key (if caching is enabled)
 * @param int $cache_ttl Cache TTL in seconds (if caching is enabled)
 * @return int|null Number of rows or null on error
 */
function db_count($query, $types = "", $params = [], $cache_key = null, $cache_ttl = CACHE_TTL) {
    // Try to get from cache first if caching is enabled and key provided
    if (CACHE_ENABLED && $cache_key !== null) {
        $redis = get_redis_connection();
        if ($redis !== null) {
            $cached_data = $redis->get($cache_key);
            if ($cached_data !== false) {
                return (int)$cached_data;
            }
        }
    }
    
    $stmt = db_prepare_execute($query, $types, $params);
    if (!$stmt) {
        return null;
    }
    
    $result = $stmt->get_result();
    $count = $result->num_rows;
    
    $stmt->close();
    
    // Store in cache if caching is enabled and key provided
    if (CACHE_ENABLED && $cache_key !== null) {
        $redis = get_redis_connection();
        if ($redis !== null) {
            $redis->setex($cache_key, $cache_ttl, (string)$count);
        }
    }
    
    return $count;
}

/**
 * Begin a transaction
 * 
 * @return bool True on success, false on failure
 */
function db_begin_transaction() {
    $conn = get_db_connection();
    return $conn->begin_transaction();
}

/**
 * Commit a transaction
 * 
 * @return bool True on success, false on failure
 */
function db_commit() {
    $conn = get_db_connection();
    return $conn->commit();
}

/**
 * Rollback a transaction
 * 
 * @return bool True on success, false on failure
 */
function db_rollback() {
    $conn = get_db_connection();
    return $conn->rollback();
}

/**
 * Invalidate multiple cache keys
 * 
 * @param array $cache_keys Cache keys to invalidate
 * @return bool True if all keys were invalidated, false otherwise
 */
function invalidate_cache_keys($cache_keys) {
    if (!CACHE_ENABLED || empty($cache_keys)) {
        return true;
    }
    
    $redis = get_redis_connection();
    if ($redis === null) {
        return false;
    }
    
    $success = true;
    foreach ($cache_keys as $key) {
        if (!$redis->del($key)) {
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Clear the entire cache
 * 
 * @return bool True on success, false on failure
 */
function clear_cache() {
    if (!CACHE_ENABLED) {
        return true;
    }
    
    $redis = get_redis_connection();
    if ($redis === null) {
        return false;
    }
    
    return $redis->flushDb();
}

/**
 * Close database connection
 */
function db_close() {
    global $db_connection;
    
    if ($db_connection !== null) {
        $db_connection->close();
        $db_connection = null;
    }
}

/**
 * Close Redis connection
 */
function redis_close() {
    global $redis;
    
    if ($redis !== null) {
        $redis->close();
        $redis = null;
    }
}

// Register shutdown function to close connections
register_shutdown_function(function() {
    db_close();
    redis_close();
});

// Session handler for database-based sessions
if (SESSION_SAVE_HANDLER === 'database') {
    class DatabaseSessionHandler implements SessionHandlerInterface {
        private $conn;
        
        public function open($savePath, $sessionName) {
            $this->conn = get_db_connection();
            return true;
        }
        
        public function close() {
            return true;
        }
        
        public function read($id) {
            $stmt = db_prepare_execute("SELECT data FROM sessions WHERE id = ?", "s", [$id]);
            if (!$stmt) {
                return "";
            }
            
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            
            return $row ? $row['data'] : "";
        }
        
        public function write($id, $data) {
            $access = time();
            
            $stmt = db_prepare_execute(
                "REPLACE INTO sessions (id, access, data) VALUES (?, ?, ?)",
                "sis",
                [$id, $access, $data]
            );
            
            if (!$stmt) {
                return false;
            }
            
            $stmt->close();
            return true;
        }
        
        public function destroy($id) {
            $stmt = db_prepare_execute("DELETE FROM sessions WHERE id = ?", "s", [$id]);
            if (!$stmt) {
                return false;
            }
            
            $stmt->close();
            return true;
        }
        
        public function gc($maxlifetime) {
            $old = time() - $maxlifetime;
            
            $stmt = db_prepare_execute("DELETE FROM sessions WHERE access < ?", "i", [$old]);
            if (!$stmt) {
                return false;
            }
            
            $stmt->close();
            return true;
        }
    }
    
    // Create and set the session handler
    $sessionHandler = new DatabaseSessionHandler();
    session_set_save_handler($sessionHandler, true);
}