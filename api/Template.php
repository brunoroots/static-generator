<?php 
namespace StaticGenerator;

use Directus\Util\ArrayUtils;
use Directus\Database\TableGatewayFactory as TableFactory;
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
     * @var unknown
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
        $this->adapter = $adapter;
        $this->type = ArrayUtils::get($data, 'type');  
        $this->filePath = ArrayUtils::get($data, 'filePath');  
        $this->id = ArrayUtils::get($data, 'id', $this->encodeTemplateId());  
        $this->route = $this->getRouteFromFilepath();      
        $this->contents = ArrayUtils::get($data, 'contents'); 
        $this->isMultiPage = strpos($this->route, '{{') !== false;
 
        if( ! $this->contents && $this->exists()) {
            $this->contents = $this->adapter->read( $this->type . '/' . $this->filePath );
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
        }
    }
    
    /**
     * Validate template data
     * 
     * @throws Exception
     */
    public function validate()
    {
        try {       
            if( ! $this->filePath) {
                throw new Exception('Invalid template file path.');
            }
            
            if( ! in_array($this->type, self::$supportedTemplateTypes)) {
                throw new Exception('Invalid template type.');
            }
            
            // file must be html if it's an include       
            if($this->type == 'include') {     
                $segments = array_filter(explode(DIRECTORY_SEPARATOR, $this->route));
                $fileName = array_pop($segments);
                
                if ( substr($fileName, -5) != '.html') {
                    throw new Exception('The file extension must be `.html`.  Ex - `' . $fileName . '.html`.');
                }
            }
        }
        
        catch (Exception $e) {
            throw $e;
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
            $this->validate();
            
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
                $tokens = $twig->tokenize($source);
                            
                
                if( ! $tokens->isEOF()) {
                    while($tokens->next()) {
                        
                        $token = $tokens->getCurrent();
                        
                        if( $tokens->isEOF()) break;
                        
                        if($token->getValue() != 'directus') continue;
                        
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

                    try {
                        $table = TableFactory::create(ArrayUtils::get($token, 'table'));
                    }
                    
                    catch (\Directus\Database\Exception\TableNotFoundException $e) {
                        continue;
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
    public function parseTemplate()
    {
        try {   
            if( $this->type != 'page') {
                throw new Exception('Invalid template type.  Must be of type `page`.');
            }
            
            $tokenMap = [];
            $routeTokens = [];
            $output = [];
            
            if($this->isMultiPage) {
                $routeTokens = $this->tokenizeRoute();
                $tokenMap = ['this' => $routeTokens];
            }
                
            $templateTokens = $this->tokenizeTemplate($tokenMap);
            $data = $this->queryData( $templateTokens );
    
            $twig = new \Twig_Environment( new \Twig_Loader_Filesystem( $this->adapter->getAdapter()->getPathPrefix()) );
            
            $compiledPaths = [];
            foreach(self::getAll($this->adapter) as $template) {
                
                $source = $twig->getLoader()->getSource($template->type . '/' . $template->filePath);
                
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

                $routeItems = array_shift($this->queryData([$routeTokens]));
                $hash = ArrayUtils::get($routeItems, 'hash');
                ArrayUtils::remove($routeItems, 'hash');
                
                foreach($routeItems as $key => $val) {

                    $directusData['directus'][$hash] = $val;
                    
                    $output[] = [
                        'contents' => $template->render($directusData),
                        'routePath' => str_replace(
                            ArrayUtils::get($routeTokens, 'routeExpression'), 
                            ArrayUtils::get($val, ArrayUtils::get($routeTokens, 'field')),
                            $this->filePath),
                    ];
                }
            }

            else {
                           
                $output[] = [
                    'contents' => $template->render($directusData),
                    'routePath' => $this->filePath,
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
           
            if($compiledPaths) {
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
            $template = new Template($adapter);
            $template->id = $id;
            $decoded = $template->decodeTemplateId();
            
            if( ! $decoded) {
                throw new Exception('Template not found.');
            }
            
           $segments = explode('/', $decoded);
           
           $template->type = array_shift($segments);
           $template->filePath = implode('/', $segments);
           $template->route = $template->getRouteFromFilePath();
           $template->id = $id;
           
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
    public function getRouteFromFilepath()
    {        
        try {
           if( $this->type == 'include') return $this->filePath;
           
           $segments = explode('/', $this->filePath);
           array_pop($segments);
           
           return '/' . implode('/', $segments);
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
        return base64_encode($this->type . '/' . $this->filePath);
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