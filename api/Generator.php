<?php 
namespace StaticGenerator;

use Directus\Util\ArrayUtils;
use Directus\Database\TableGatewayFactory as TableFactory;
use League\Flysystem\FilesystemInterface as FlysystemInterface;

class Generator {
        
    /**
     * Storage adapter for reading templates
     * 
     * @var League\Flysystem\FilesystemInterface
     */
    private $inputStorageAdapter;
        
    /**
     * Storage adapter for writing generated files
     * 
     * @var League\Flysystem\FilesystemInterface
     */
    private $outputStorageAdapter;
    
    
    /**
     * Constructor
     * 
     * @param League\Flysystem\FilesystemInterface $inputStorageAdapter
     * @param League\Flysystem\FilesystemInterface $outputStorageAdapter
     */
    public function __construct(FlysystemInterface $inputStorageAdapter, FlysystemInterface $outputStorageAdapter)
    {
        $this->inputStorageAdapter = $inputStorageAdapter;
        $this->outputStorageAdapter = $outputStorageAdapter;
    }
    
    /**
     * Extract Directus tokens from template, i.e - `directus.articles`
     * 
     * @param string $templatePath - relative to $this->inputStorageAdapter template path
     * @throws Exception
     * @return array
     */
    public function tokenizeTemplate($templatePath)
    {
        try {            
            $twig = new \Twig_Environment( new \Twig_Loader_Filesystem($this->inputStorageAdapter->getAdapter()->getPathPrefix()) );
            $source = $twig->getLoader()->getSource($templatePath);
            $tokens = $twig->tokenize($source);
         
            $templateTokens = [];
            if( ! $tokens->isEOF()) {
                while($tokens->next()) {
                    
                    $token = $tokens->getCurrent();
                    
                    if( $tokens->isEOF()) break;
                    
                    if($token->getValue() != 'directus') continue;
                    
                    if($tokens->look()->getValue() == '.') {                    
                        $tokens->next();
                        $templateTokens[] = [
                            'table' => $tokens->look()->getValue()  
                        ];
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
     * Convert Directus tokens to Twig data
     * 
     * @param array $templateTokens
     * @param array $filters
     * @throws Exception
     * @return array
     */
    public function queryData($templateTokens = [], $filters = [])
    {
        try {
            $data = [];
            if($templateTokens) {            
                foreach($templateTokens as $token) {            
                    $table = TableFactory::create(ArrayUtils::get($token, 'table'));
                    $data[ArrayUtils::get($token, 'table')] = ArrayUtils::get($table->getItems(['filters' => $filters]), 'data');
                }
            }
            
            return $data;
        }
        
        catch (Exception $e) {
            throw $e;
        }
    }
    
    
    public function tokenizeRoute($route)
    {
        //$route = '/articles/{{articles.id | filters[date_published][gt]=now()}}';
        //         $route = $app->request()->post('route');
        //         preg_match('/{{(.*?)}}/', $route, $match);
        
        //         if($match) {
        
        //             $query = ArrayUtils::get($match, 1);
        //             if($query) {
        //                 $queryParts = explode('|', $query);
        //                 $variable = array_shift($queryParts);
        
        //                 $variableParts = explode('.', $variable);
        //                 $table = array_shift($variableParts);
        //                 $column = trim(array_shift($variableParts));
        
        //                 parse_str(array_shift($queryParts), $filters);
        
        //                 $table = TableFactory::create($table);
        //                 $entries = ArrayUtils::get($table->getItems(['filters' => $filters]), 'data');
        
        //                 if($entries) {
        //                     foreach($entries as $entry) {
        
        //                         $col = ArrayUtils::get($entry, $column);
        
        //                         if($col){
        
        //                             $template->saveTemplate([
        //                                 'route' => str_replace(ArrayUtils::get($match, 0), $col, $route),
        //                                 'type' => 'page',
        //                                 'contents' => 'test',
        //                             ]);
        //                         }
        //                     }
        //                 }
        
        //                 return $app->response([
        //                     'success' => true,
        //                     'message' => 'Request successfully processed.',
        //                 ]);
        //             }
        //         }
    }
    
    /**
     * Parse template and return generated output
     * 
     * @param string $templatePath - relative to $this->inputStorageAdapter template path
     * @param array $data
     * @throws Exception
     */
    public function parseTemplate($templatePath, $data = [])
    {
        try {            
            $twig = new \Twig_Environment( new \Twig_Loader_Filesystem( $this->inputStorageAdapter->getAdapter()->getPathPrefix()) );
            $template = $twig->load($templatePath);
            
            $directusData['directus'] = $data;
            return $template->render($directusData);
        }
        
        catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Create output file
     * 
     * @param string $outputFilePath
     * @param string $contents
     * @throws Exception
     */
    public function saveOutput($outputFilePath, $contents)
    {
        try {                  
            $this->outputStorageAdapter->put($outputFilePath, $contents);
        }
        
        catch (Exception $e){
            throw $e;
        }
    }
    
    /**
     * Generate entire site
     * 
     * @param $templatesPath - relative to $this->inputStorageAdapter template path
     * @throws Exception
     * @return boolean
     */
    public function generateSite($templatesPath)
    {
        try {         
            $templates = $this->inputStorageAdapter->listContents($templatesPath, true);      

            foreach($templates as $template) {
                
                if( ArrayUtils::get($template, 'type') != 'file') continue;
                                
                $templateTokens = $this->tokenizeTemplate(ArrayUtils::get($template, 'path'));
                $data = $this->queryData($templateTokens);
                $output = $this->parseTemplate(ArrayUtils::get($template, 'path'), $data);
                $this->saveOutput(str_replace($templatesPath, '', ArrayUtils::get($template, 'path')), $output);
            }  
            
            return true;
        }
        
        catch (Exception $e){
            throw $e;
        }
    }
} 