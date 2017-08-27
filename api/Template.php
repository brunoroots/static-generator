<?php 
namespace StaticGenerator;

use Directus\Util\ArrayUtils;
use Directus\Database\TableGatewayFactory as TableFactory;
use League\Flysystem\FilesystemInterface as FlysystemInterface;
use Exception;

class Template{
        
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
    private $supportedTemplateTypes = ['page', 'include'];
    
    
    /**
     * Constructor
     * 
     * @param array $opts
     */
    public function __construct(FlysystemInterface $adapter, $options = [])
    {
        $this->adapter = $adapter;
        $this->supportedTemplateTypes = ArrayUtils::get($options, 'supportedTemplateTypes', $this->supportedTemplateTypes);
    }
    
    /**
     * Validate template data
     * 
     * @param array $template
     * @throws Exception
     */
    public function validateTemplate($template = [])
    {
        try {            
            if( ! ArrayUtils::get($template, 'route')) {
                throw new Exception('You must provide either a route or filename to create a new template file.');
            }
            
            if( ! in_array(ArrayUtils::get($template, 'type'), $this->supportedTemplateTypes)) {
                throw new Exception('Invalid template type.');
            }
            
            // TEMPORARY    
            if( strpos(ArrayUtils::get($template, 'route'), '{{') !== false) {
                throw new Exception('Multi-page templates are not yet supported.');
            }
            
            // file must be html if it's an include       
            if(ArrayUtils::get($template, 'type') == 'include') {     
                $segments = array_filter(explode(DIRECTORY_SEPARATOR, ArrayUtils::get($template, 'route')));
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
     * Create/update template file
     * 
     * @param array $template
     * @throws Exception
     */
    public function saveTemplate($template = ['id' => null, 'route' => null, 'type' => null, 'contents' => ''])
    {
        try {      
            $this->validateTemplate($template);
            
            // update
            if(ArrayUtils::get($template, 'id')) {
                $path = $this->decodeTemplateId(ArrayUtils::get($template, 'id'));
            }
            
            // create
            else { 
                $route = ArrayUtils::get($template, 'route');
                if(ArrayUtils::get($template, 'type') == 'page') {
                    $route .= '/index.html';
                }              
                
                $id = $this->encodeTemplateId(['file' => $route, 'type' => ArrayUtils::get($template, 'type')]);
                if( $this->templateExists($id)) {
                    throw new Exception('This file already exists.');
                }
                
                $path = ArrayUtils::get($template, 'type') . '/' . $route;
            }
            
            $this->adapter->put($path, ArrayUtils::get($template, 'contents'));
        }
        
        catch (Exception $e){
            throw $e;
        }
    }
    
    /**
     * Delete template file
     * 
     * @param int $id
     * @throws Exception
     */
    public function deleteTemplate($id)
    {
        try {      
            $path = $this->decodeTemplateId($id);                    
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
     * @param string $id
     * @throws Exception
     */
    public function templateExists($id)
    {
        try {                  
            return $this->adapter->has($this->decodeTemplateId($id));
        }
        
        catch (Exception $e){
            throw $e;
        }
    }
    
    /**
     * Return all template files
     * 
     * @return array
     * @throws Exception
     */
    public function getTemplates($directories = [])
    {
        try {
            if( ! $directories) {
                $directories = $this->supportedTemplateTypes;
            }
            
            $templates = [];
            foreach($directories as $dir) {
                $files = $this->adapter->listContents($dir, true);
                    
                if( ! $files) {
                    continue;
                }
                
                foreach($files as $file) {    
                    
                    if( ArrayUtils::get($file, 'type') == 'dir') {
                        continue;
                    }

                    // path to file, i.e - `page/about/index.html`
                    $segments = explode(DIRECTORY_SEPARATOR, ArrayUtils::get($file, 'path'));
                    
                    // remove type segment (page or include) to create user friendly file path
                    array_shift($segments);
                    $filePath = implode('/', $segments);
                    
                    // remove file name to create user friendly route
                    array_pop($segments);
                    $route = '/' . implode('/', $segments);

                    $templates[] = [
                        'id' => $this->encodeTemplateId(['file' => $filePath, 'type' => $dir]),
                        'route' => $route,
                        'file' => $filePath,
                        'type' => $dir,
                        'contents' => $this->adapter->read(ArrayUtils::get($file, 'path')),
                        'isPage' => $dir == 'page', 
                    ];
                }
            }
       
            return $templates;
        }
        
        catch (Exception $e){
            throw $e;
        }
    }
    
    /**
     * Encodes a route into a decodable hash
     * 
     * @param array $template
     */
    private function encodeTemplateId($template = ['file' => null, 'type' => null])
    {
        return base64_encode(ArrayUtils::get($template, 'type') . '/' . ArrayUtils::get($template, 'file'));
    }
    
    /**
     * Decodes encoded hash into a route
     * 
     * @param string $templateId
     */
    private function decodeTemplateId($templateId)
    {
        return base64_decode($templateId);
    }
} 