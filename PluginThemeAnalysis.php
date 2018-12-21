<?php
/**
 * Plugin to analyse plugins for a theme including plugins used of other plugins via theme settings file and plugin manifest file.
 */
class PluginThemeAnalysis{
  private $settings = null;
  public $data = null;
  function __construct($buto) {
    if($buto){
      /**
       * Include.
       */
      wfPlugin::includeonce('wf/array');
      wfPlugin::includeonce('wf/yml');
      /**
       * Enable.
       */
      wfPlugin::enable('datatable/datatable_1_10_16');
      /**
       * Only webmaster.
       */
      if(!wfUser::hasRole('webmaster')){
        exit('Role issue says PluginThemeAnalysis.');
      }
      /**
       * Settings.
       */
      $this->settings = new PluginWfArray(wfArray::get($GLOBALS, 'sys/settings/plugin_modules/'.wfArray::get($GLOBALS, 'sys/class').'/settings'));
    }
  }
  public function page_start(){
    /**
     * Layout path.
     */
    wfArray::set($GLOBALS, 'sys/layout_path', '/plugin/theme/analysis/layout');
    /**
     * 
     */
    wfPlugin::includeonce('wf/yml');
    $page = new PluginWfYml(__DIR__.'/page/start.yml');
    /**
     * Insert admin layout from theme.
     */
    $page = wfDocument::insertAdminLayout($this->settings, 1, $page);
    $json = json_encode(array('class' => wfGlobals::get('class')));
    $page->setByTag(array('json' => 'var app = '.$json));
    /**
     * Insert admin layout from theme.
     */
    wfDocument::mergeLayout($page->get());
  }
  public function page_analys(){
    $this->setData();
    $element = new PluginWfYml('/plugin/theme/analysis/element/table.yml');
    $trs = array();
    foreach ($this->data->get() as $key => $value) {
      $item = new PluginWfArray($value);
      $tr = new PluginWfYml('/plugin/theme/analysis/element/table_tr.yml');
      $tr->setByTag($item->get());
      $trs[] = $tr->get();
    }
    $element->setByTag(array('trs' => $trs));
    wfDocument::renderElement($element->get());
    wfHelp::yml_dump($this->data, true);
  }
  public function setData($theme = null){
    if(is_null($theme)){
      $theme = '[theme]';
    }
    $settings = new PluginWfYml("/theme/$theme/config/settings.yml");
    //wfHelp::yml_dump($settings);
    $this->data = new PluginWfArray();
    /**
     * Plugin modules.
     */
    foreach ($settings->get('plugin_modules') as $key => $value) {
      $item = new PluginWfArray($value);
      $item = $this->handleItem($item, 'plugin_modules', $settings);
      $this->data->set(str_replace('/', '.', $item->get('plugin')).'/name', $item->get('plugin'));
      $this->data->set(str_replace('/', '.', $item->get('plugin')).'/plugin_module', true);
      $this->data->set(str_replace('/', '.', $item->get('plugin')).'/version', $item->get('version'));
      //$this->data->set(str_replace('/', '.', $item->get('plugin')).'/version', $settings->get('plugin/'.$item->get('plugin').'/version'));
    }
    /**
     * Plugin.
     */
    foreach ($settings->get('plugin') as $key => $value) {
      foreach ($value as $key2 => $value2) {
        $item = new PluginWfArray($value2);
        $item = $this->handleItem($item, 'plugin', $settings);
        $this->data->set($key.'.'.$key2.'/name', $key.'/'.$key2);
        if($item->get('enabled')){
          $this->data->set($key.'.'.$key2.'/plugin', true);
        }
        $this->data->set($key.'.'.$key2.'/version', $item->get('version'));
      }
    }
    /**
     * Event.
     */
    if($settings->get('events')){
      foreach ($settings->get('events') as $key => $value) {
        foreach ($value as $key2 => $value2) {
          $item = new PluginWfArray($value2);
          $item = $this->handleItem($item, 'events', $settings);
          $this->data->set(str_replace('/', '.', $value2['plugin']).'/name', $value2['plugin']);
          $this->data->set(str_replace('/', '.', $value2['plugin']).'/event', true);
          $this->data->set(str_replace('/', '.', $value2['plugin']).'/version', $item->get('version'));
          //$this->data->set(str_replace('/', '.', $value2['plugin']).'/version', $settings->get('plugin/'.$value2['plugin'].'/version'));
        }
      }
    }
    /**
     * Manifest from theme plugin.
     */
    foreach ($this->data->get() as $key => $value) {
      $this->setManifest($key, $value);
    }
    /**
     * Manifest from inherit plugins.
     */
    for($i=0;$i<100;$i++){
      foreach ($this->data->get() as $key => $value) {
        $item = new PluginWfArray($value);
        if(!is_array($item->get('manifest')) && $item->get('manifest')!==false){
          $this->setManifest($key, $value);
        }
      }
    }
    /**
     * Modify data.
     */
    foreach ($this->data->get() as $key => $value) {
      $item = new PluginWfArray($value);
      $version_conflict = '';
      if($item->get('manifest')===false){
        $this->data->set("$key/has_manifest", '');
        $this->data->set("$key/manifest_plugin", '');
      }else{
        $this->data->set("$key/has_manifest", 'Yes');
        $str = null;
        if($item->get('manifest/plugin')){
          foreach ($item->get('manifest/plugin') as $key2 => $value2) {
            $item2 = new PluginWfArray($value2);
            
            $version = $this->data->get(str_replace('/', '.', $item2->get('name')).'/manifest/version');
            $star = null;
            if($item2->get('version')!=$version){
              $version_conflict = 'Yes';
              $star = '*';
            }
            $str .= $item2->get('name').'('.$item2->get('version').''.$star.'), ';
          }
          $str = substr($str, 0, strlen($str)-2);
        }
        $this->data->set("$key/manifest_plugin", $str);
      }
      $this->data->set("$key/version_conflict", $version_conflict);
      if(is_null($item->get('version'))){
        $this->data->set("$key/version", '(inherit)');
      }
      if($item->get('plugin_module')==1){
        $this->data->set("$key/plugin_module", 'Yes');
      }else{
        $this->data->set("$key/plugin_module", '');
      }
      if($item->get('plugin')==1){
        $this->data->set("$key/plugin", 'Yes');
      }else{
        $this->data->set("$key/plugin", '');
      }
      if($item->get('event')==1){
        $this->data->set("$key/event", 'Yes');
      }else{
        $this->data->set("$key/event", '');
      }
      if($this->data->get("$key/version")!= '(inherit)' && $item->get('version')!=$item->get('version_manifest')){
        $this->data->set("$key/theme_version_conflict", 'Yes');
      }else{
        $this->data->set("$key/theme_version_conflict", '');
      }
    }
  }
  private function handleItem($item, $type, $settings){
    //wfHelp::yml_dump(array($type, $item->get()));
    if($type=='plugin'){
      $version = $item->get('version');
    }else{
      $version = $settings->get('plugin/'.$item->get('plugin').'/version');
    }
    if(!$version){
      $item->set('version', '(not set)');
    }else{
      $item->set('version', $version);
    }
    return $item;
  }
  private function setManifest($key, $value){
    $item = new PluginWfArray($value);
    $filename = wfGlobals::getAppDir().'/plugin/'.$item->get('name').'/manifest.yml';
    if(wfFilesystem::fileExist($filename)){
      $manifest = new PluginWfYml($filename);
      $this->data->set("$key/manifest", $manifest->get());
      $this->data->set("$key/version_manifest", $manifest->get('version'));
      if(is_array($manifest->get('plugin'))){
        foreach ($manifest->get('plugin') as $key2 => $value2) {
          $item = new PluginWfArray($value2);
          $this->data->set(str_replace('/', '.', $value2['name']).'/name', $value2['name']);
          $this->data->set(str_replace('/', '.', $value2['name']).'/inherit', true);
        }
      }
    }else{
      $this->data->set("$key/manifest", false);
      $this->data->set("$key/version_manifest", null);
    }
  }
}
