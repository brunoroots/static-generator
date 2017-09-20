<?php 
namespace StaticGenerator;

use Directus\Util\ArrayUtils;
use Directus\Database\TableGatewayFactory as TableFactory;
use Directus\Database\Exception\TableNotFoundException;
use League\Flysystem\FilesystemInterface as FlysystemInterface;
use Exception;

class Template {
        
    /**
     * Storage adapter
     * 
     * @var League\Flysystem\FilesystemInterface
     */
    private $adapter;
    
    /**
     * Template types
     * 
     * @var array
     */
    public static $supportedTemplateTypes = ['page', 'include'];
    
    /**
     * Type of template (`page` or `include`)
     * @var unknown
     */
    private $type;
    
    /**
     * File path to template 
     * 
     * @var string
     */
    private $filePath;
    
    /**
     * Generated page id
     * 
     * @var string
     */
    private $id;
    
    /**
     * Page route
     * 
     * @var string
     */
    private $route;
    
    /**
     * Template source code
     * 
     * @var string
     */
    private $contents;
    
    /**
     * Is page a `multi-page`?
     * Multi result in one or more generated files per template.
     * For example, `/articles/{{ article.id }}` would result in one generated html file per article.
     * 
     * @var bool
     */
    private $isMultiPage;    
    
    /**
     * Constructor
     * 
     * @param FlysystemInterface $adapter
     * @param array $data
     */
    public function __construct(FlysystemInterface $adapter, $data = [])
    {      
        try {  
            $this->adapter = $adapter;
            
            // must provide either `type` and `filePath`, or `id`
            if( ArrayUtils::get($data, 'type') && ArrayUtils::get($data, 'filePath')) {
                $this->type = ArrayUtils::get($data, 'type');  
                $this->filePath = ArrayUtils::get($data, 'filePath');      
                $this->id = $this->encodeTemplateId();        
            }
            
            else if( ArrayUtils::get($data, 'id') ) {
                $this->id = ArrayUtils::get($data, 'id');
                
                $path = $this->decodeTemplateId();
                $pathParts = explode('/', $path);
                
                $this->type = array_shift($pathParts);
                $this->filePath = implode('/', $pathParts); 
            }
                
            else {
                throw new Exception('You must pass either a template `id` or `type` and `filePath`');
            }
              
            $this->contents = ArrayUtils::get($data, 'contents'); 
     
            if( ! $this->contents && $this->exists()) {
                $this->contents = $this->adapter->read( $this->type . '/' . $this->filePath );
            }  
            
            $this->route = $this->extractRoute();
            $this->isMultiPage = strpos($this->route, '{{') !== false;
        }
        
        catch (Exception $e){
            throw $e;
        }    
    }
    
    /**
     * Magic getter
     * 
     * @param string $name
     * @return mixed
     */
    public function __get($name) 
    {
        return isset($this->{$name}) ? $this->{$name} : null;
    }
    
    /**
     * Magic setter
     * 
     * @param string $name
     * @param mixed $val
     */
    public function __set($name, $val) 
    {
        if( property_exists($this, $name)) {
            
            $this->{$name} = $val;  
            
            if( $name == 'filePath') {
                $this->id = $this->encodeTemplateId();
            }
        }
    }
    
    /**
     * Create template file
     * 
     * @throws Exception
     */
    public function save()
    {
        try {           
                        
            $path = $this->decodeTemplateId($this->id);            
            $this->adapter->put($path, $this->contents);
        }
        
        catch (Exception $e){
            throw $e;
        }
    }
    
    /**
     * Delete template file
     * 
     * @throws Exception
     */
    public function delete()
    {
        try {      
            $path = $this->decodeTemplateId($this->id);                    
            $this->adapter->delete($path);            
            
            $segments = explode('/', $path);
            while(count($segments) > 1) {
                
                array_pop($segments);
                $dir = implode('/', $segments);
                if( ! $this->adapter->listContents($dir, true)) {
                    $this->adapter->deleteDir($dir);
                }
            }
        }
        
        catch (Exception $e){
            throw $e;
        }
    }
    
    /**
     * Check if template exists, give id
     * 
     * @return bool
     * @throws Exception
     */
    public function exists()
    {
        try {           
            return $this->adapter->has($this->decodeTemplateId());
        }
        
        catch (Exception $e){
            throw $e;
        }
    }
    
    /**
     * Extract Directus tokens from template, i.e - `directus.articles`
     * 
     * @param array $tokenMap
     * @return array
     * @throws Exception
     */
    public function tokenizeTemplate($tokenMap = [])
    {
        try {            
            $twig = new \Twig_Environment( new \Twig_Loader_Filesystem($this->adapter->getAdapter()->getPathPrefix()) );
            
            $templateTokens = [];
            
            foreach(self::getAll($this->adapter) as $template) {
            
                $source = $twig->getLoader()->getSource($template->type . '/' . $template->filePath);
                
                // remove directus directives
                $source = preg_replace('/<!---(.*?)--->/', '', $source);
                                
                $tokens = $twig->tokenize($source);
                            
                
                if( ! $tokens->isEOF()) {
                    while($tokens->next()) {
                        
                        $token = $tokens->getCurrent();
                        
                        if( $tokens->isEOF()) break;
                        
                        if( $token->getValue() != 'directus') continue;
                        
                        if($tokens->look()->getValue() == '.') {                    
                            $tokens->next(); // dot
                            $tokens->next(); // table 
                            
                            $token = $tokens->getCurrent();                        
                            $table = trim($token->getValue());
                        }
                        
                        $param = [];
                        if($tokens->look()->getValue() == '(') {     
    
                            $tokens->next(); // advance
                            
                            // collect passed param
                            while($tokens->next()) {
                        
                                $token = $tokens->getCurrent();
                                
                                if( $tokens->isEOF()) break;
                                
                                if($token->getValue() == ')') break;
                                
                                $param[] = $token->getValue();
                            }
                        }
    
                        // re-map `this` token, if needed
                        if( array_key_exists($table, $tokenMap)) {
                            
                            $templateTokens[] = [
                                'table' => ArrayUtils::get($tokenMap, $table . '.table'),
                                'field' => ArrayUtils::get($tokenMap, $table . '.field'),
                                'param' => ArrayUtils::get($tokenMap, $table . '.param'),
                                'expression' => ArrayUtils::get($tokenMap, $table . '.table') . ( ArrayUtils::get($tokenMap, $table . '.param')? '("' . ArrayUtils::get($tokenMap, $table . '.param') . '")' : '' )
                            
                            ];
                        }
                        
                        else {
                            $templateTokens[] = [
                                'table' => $table,
                                'field' => null,
                                'param' => trim(implode('', $param)),
                                'expression' => $table . ( $param ? '("' . trim(implode('', $param)) . '")' : '' )
                            ];
                        }
                    }
                }
            }
        
            return $templateTokens;
        }
        
        catch (Exception $e) {
            throw $e;
        }        
    }
    
    /**
     * Tokenize route
     * 
     * @return array
     * @throws Exception
     */
    public function tokenizeRoute()
    {
        try {         
            $segments = explode('/', $this->route);
            $last = array_pop($segments);
               
            preg_match('/{{(.*?)}}/', $last, $expressionMatch);
            $expression = ArrayUtils::get($expressionMatch, 1);
            
            if( ! $expression) return false;
            
            $expressionParts = explode('|', $expression);
            $variable = array_shift($expressionParts);
            $variableParts = explode('.', $variable);
            
            $table = ArrayUtils::get($variableParts, 0);
            $field = ArrayUtils::get($variableParts, 1);
            
            if( ! $table || ! $field) return false;
            
            $param = array_shift($expressionParts);
            
            return [
                'table' => trim($table),
                'field' => trim($field),
                'param' => trim($param),
                'expression' => trim($table) . ( $param ? '("' . trim($param) . '")' : '' ),
                'routeExpression' => ArrayUtils::get($expressionMatch, 0),
            ];
        }
        
        catch (Exception $e) {
            throw $e;
        }  
    }
    
    /**
     * Convert Directus tokens to Twig-ready data
     * 
     * @param array $templateTokens
     * @return array
     * @throws Exception
     */
    public function queryData($templateTokens = [])
    {
        try {
            $data = [];
            if($templateTokens) {            
                foreach($templateTokens as $token) {
                    
                    if( ArrayUtils::get($token, 'tableObj')) {
                        
                        $table = ArrayUtils::get($token, 'tableObj');
                    }

                    else {
                        
                        try {
                            $table = TableFactory::create(ArrayUtils::get($token, 'table'));
                        }
                        
                        catch (TableNotFoundException $e) {
                            continue;
                        }
                    }
                    
                    parse_str(ArrayUtils::get($token, 'param'), $param);
                    
                    $key = ArrayUtils::get($token, 'table') . ( ArrayUtils::get($token, 'param') ? '("' . ArrayUtils::get($token, 'param') . '")' : '' );
                    $data[$key] = ArrayUtils::get($table->getItems($param), 'data');
                    $data[$key]['hash'] =  '_' . md5($key);
                    $data['_' . md5($key)] = $data[$key];
                }
            }
            
            $keys = array_map('strlen', array_keys($data));
            array_multisort($keys, SORT_DESC, $data);
            
            return $data;
        }
        
        catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Parse template and return generated output
     * 
     * @return array
     * @throws Exception
     */
    public function parseTemplate($templateTokens = [], $routeTokens = [])
    {
        try {   
            if( $this->type != 'page') {
                throw new Exception('Invalid template type.  Must be of type `page`.');
            }
            
            $tokenMap = [];
            $output = [];
            
            if($this->isMultiPage) {
                $routeTokens = $routeTokens ? $routeTokens : $this->tokenizeRoute();
                $tokenMap = ['this' => $routeTokens];
            }
           
            $templateTokens = $templateTokens ? $templateTokens : $this->tokenizeTemplate($tokenMap);
            $data = $this->queryData( $templateTokens );
    
            $twig = new \Twig_Environment( new \Twig_Loader_Filesystem( $this->adapter->getAdapter()->getPathPrefix()) );
            
            $compiledPaths = [];
            foreach(self::getAll($this->adapter) as $template) {
                
                $source = $twig->getLoader()->getSource($template->type . '/' . $template->filePath);
                
                // remove directus directives
                $source = preg_replace('/<!---(.*?)--->/', '', $source);
                
                if($tokenMap) {
                    foreach($tokenMap as $key => $val) {
                        $source = str_replace('directus.' . $key, 'directus.' . ArrayUtils::get($val, 'expression'), $source);
                    }
                }
                            
                if($data) {
                    foreach($data as $key => $val) {
                        if( $key == 'hash') continue;
                        $source = str_replace('directus.' . $key, 'directus.' . ArrayUtils::get($val, 'hash'), $source);                    
                        
                    }
                }
                
                $this->adapter->put($template->type . '/' . $template->filePath . '._locked', $twig->getLoader()->getSource($template->type . '/' . $template->filePath));
                $this->adapter->put($template->type . '/' . $template->filePath, $source);
                $compiledPaths[] = $template->type . '/' . $template->filePath;
            }

            if($data) {
                foreach($data as $key => $val) {
                    ArrayUtils::remove($data[$key], 'hash');
                }
            }

            $twig = new \Twig_Environment( new \Twig_Loader_Filesystem( $this->adapter->getAdapter()->getPathPrefix()) );
            $template = $twig->load($this->type . '/' . $this->filePath);

            $directusData['directus'] = $data;
            
            if($this->isMultiPage) {

                $routeItems = $this->queryData([$routeTokens]);
                $routeItems = array_shift($routeItems);
                $hash = ArrayUtils::get($routeItems, 'hash');
                ArrayUtils::remove($routeItems, 'hash');
                
                foreach($routeItems as $key => $val) {

                    $directusData['directus'][$hash] = $val;
                    $output[] = [
                        'contents' => $template->render($directusData),
                        'routePath' => str_replace(
                            ArrayUtils::get($routeTokens, 'routeExpression'), 
                            ArrayUtils::get($val, ArrayUtils::get($routeTokens, 'field')),
                            $this->route) . '/index.html',
                    ];
                }
            }

            else {
                           
                $output[] = [
                    'contents' => $template->render($directusData),
                    'routePath' => $this->route . '/index.html',
                ];
            }
           
            if($compiledPaths) {
                foreach($compiledPaths as $path) {
                    $this->adapter->delete($path);
                    $this->adapter->rename($path . '._locked', $path);
                }
            }

            return $output;
        }
        
        catch (Exception $e) {
           
            if( isset($compiledPaths) && $compiledPaths ) {
                foreach($compiledPaths as $path) {
                    $this->adapter->delete($path);
                    $this->adapter->rename($path . '._locked', $path);
                }
            }
            throw $e;
        }
    }
    
    /**
     * Return all templates
     * 
     * @param FlysystemInterface $adapter
     * @return array
     * @throws Exception
     */
    public static function getAll(FlysystemInterface $adapter )
    {
        try {
            $directories = self::$supportedTemplateTypes;            
            $templates = [];
            
            foreach($directories as $dir) {
                
                $files = $adapter->listContents($dir, true);
                    
                if( ! $files) continue;
                
                foreach($files as $file) {    
                    
                    if( ArrayUtils::get($file, 'type') == 'dir') continue;
                    
                    if(substr(ArrayUtils::get($file, 'basename'), -5) != '.html') continue;

                    $segments = explode(DIRECTORY_SEPARATOR, ArrayUtils::get($file, 'path'));
                    
                    array_shift($segments);
                    $filePath = implode('/', $segments);
                    
                    $templates[] = new Template($adapter, [
                        'filePath' => $filePath,
                        'type' => $dir,
                    ]);
                }
            }
       
            return $templates;
        }
        
        catch (Exception $e){
            throw $e;
        }
    }
    
    /**
     * Return template by id
     * 
     * @param FlysystemInterface $adapter
     * @param string $id
     * @return \StaticGenerator\Template
     * @throws Exception
     */
    public static function getById(FlysystemInterface $adapter, $id)
    { 
        try {
            $template = new Template($adapter, ['id' => $id]);
            $decoded = $template->decodeTemplateId();
            
            if( ! $decoded) {
                throw new Exception('Template not found.');
            }
            
           $segments = explode('/', $decoded);
           
           $template->type = array_shift($segments);
           $template->filePath = implode('/', $segments);
           $template->route = $template->extractRoute();
           
           if( ! $template->exists()) {
               throw new Exception('Template not found.');
           }
 
            if( ! $template->contents ) {
                $template->contents = $template->adapter->read( $template->type . '/' . $template->filePath );
            }  
           
           return $template;
        }
        
        catch (Exception $e) {
            throw $e;
        }  
    }
    
    /**
     * Generate and return a route, given a file path
     * 
     * @return string
     * @throws Exception
     */
    public function extractRoute()
    {        
        try {
           preg_match('/directus_route:(.*?)--->/', $this->contents, $matches);
           
           return trim(ArrayUtils::get($matches, 1, ''));
        }
        
        catch (Exception $e) {
            throw $e;
        }  
    }
    
    /**
     * Encodes a route into a decodable hash
     * 
     * @return string
     */
    private function encodeTemplateId()
    {
        $res = base64_encode($this->type . '/' . $this->filePath);
        $res = str_replace('=', '', $res);
        return $res;
    }
    
    /**
     * Decodes encoded hash into a route
     * 
     * @return string
     */
    private function decodeTemplateId()
    {
        return base64_decode($this->id);
    }
} 