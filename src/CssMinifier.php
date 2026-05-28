<?php
/**
 * CSS Minifier.
 *
 * @package        CssMin/Minifier
 * @link        http://code.google.com/p/cssmin/
 * @author        Joe Scylla <joe.scylla@gmail.com>
 * @copyright    2008 - 2011 Joe Scylla <joe.scylla@gmail.com>
 * @license        http://opensource.org/licenses/mit-license.php MIT License
 * @version        3.0.1
 */
class CssMinifier
{
	/**
	 * {@link aCssMinifierFilter Filters}.
	 *
	 * @var array
	 */
	private $filters = array();
	/**
	 * {@link aCssMinifierPlugin Plugins}.
	 *
	 * @var array
	 */
	private $plugins = array();
	/**
	 * Minified source.
	 *
	 * @var string
	 */
	private $minified = "";
	/**
	 * Plugin class-name → index cache (per-instance, built lazily).
	 *
	 * @var array|null
	 */
	private ?array $pluginIndex = null;


	/**
	 * Constructer.
	 *
	 * Creates instances of {@link aCssMinifierFilter filters} and {@link aCssMinifierPlugin plugins}.
	 *
	 * @param string $source CSS source [optional]
	 * @param array $filters Filter configuration [optional]
	 * @param array $plugins Plugin configuration [optional]
	 * @return void
	 */
	public function __construct($source = null, ?array $filters = null, ?array $plugins = null)
	{
		$filters = array_merge(array
		(
			"ImportImports" => false,
			"RemoveComments" => true,
			"RemoveEmptyRulesets" => true,
			"RemoveEmptyAtBlocks" => true,
			"ConvertLevel3Properties" => false,
			"ConvertLevel3AtKeyframes" => false,
			"Variables" => true,
			"RemoveLastDelarationSemiColon" => true
		), is_array($filters) ? $filters : array());
		$plugins = array_merge(array
		(
			"Variables" => true,
			"ConvertFontWeight" => false,
			"ConvertHslColors" => false,
			"ConvertRgbColors" => false,
			"ConvertNamedColors" => false,
			"CompressColorValues" => false,
			"CompressUnitValues" => false,
			"CompressExpressionValues" => false
		), is_array($plugins) ? $plugins : array());
		// Filters
		foreach ($filters as $name => $config) {
			if ($config !== false) {
				$class = "Css" . $name . "MinifierFilter";
				$config = is_array($config) ? $config : array();
				if (class_exists($class)) {
					$this->filters[] = new $class($this, $config);
				} else {
					CssMin::triggerError(new CssError(__FILE__, __LINE__, __METHOD__ . ": The filter <code>" . $name . "</code> with the class name <code>" . $class . "</code> was not found"));
				}
			}
		}
		// Plugins
		foreach ($plugins as $name => $config) {
			if ($config !== false) {
				$class = "Css" . $name . "MinifierPlugin";
				$config = is_array($config) ? $config : array();
				if (class_exists($class)) {
					$this->plugins[] = new $class($this, $config);
				} else {
					CssMin::triggerError(new CssError(__FILE__, __LINE__, __METHOD__ . ": The plugin <code>" . $name . "</code> with the class name <code>" . $class . "</code> was not found"));
				}
			}
		}
		// --
		if ($source !== null) {
			$this->minify($source);
		}
	}


	/**
	 * Returns the minified Source.
	 *
	 * @return string
	 */
	public function getMinified()
	{
		return $this->minified;
	}


	/**
	 * Returns a plugin by class name.
	 *
	 * @param string $name Class name of the plugin
	 * @return aCssMinifierPlugin
	 */
	public function getPlugin($class)
	{
		if ($this->pluginIndex === null) {
			$this->pluginIndex = [];
			for ($i = 0, $l = count($this->plugins); $i < $l; $i++) {
				$this->pluginIndex[get_class($this->plugins[$i])] = $i;
			}
		}
		return isset($this->pluginIndex[$class]) ? $this->plugins[$this->pluginIndex[$class]] : false;
	}


	/**
	 * Minifies the CSS source.
	 *
	 * @param string $source CSS source
	 * @return string
	 */
	public function minify($source)
	{
		// Variables
		$r = "";
		$parser = new CssParser($source);
		$tokens = $parser->getTokens();
		$filters = $this->filters;
		$filterCount = count($this->filters);
		$plugins = $this->plugins;
		$pluginCount = count($plugins);
		$pluginIndex = array();
		$pluginTriggerTokens = array();
		$globalTriggerTokens = array();
		for ($i = 0, $l = count($plugins); $i < $l; $i++) {
			$tPluginClassName = get_class($plugins[$i]);
			$pluginTriggerTokens[$i] = $plugins[$i]->getTriggerTokens();
			foreach ($pluginTriggerTokens[$i] as $v) {
				if (!in_array($v, $globalTriggerTokens)) {
					$globalTriggerTokens[] = $v;
				}
			}
			$pluginTriggerTokens[$i] = "|" . implode("|", $pluginTriggerTokens[$i]) . "|";
			$pluginIndex[$tPluginClassName] = $i;
		}
		$globalTriggerTokens = "|" . implode("|", $globalTriggerTokens) . "|";
		/*
		 * Apply filters
		 */
		for ($i = 0; $i < $filterCount; $i++) {
			// Apply the filter; if the return value is larger than 0...
			if ($filters[$i]->apply($tokens) > 0) {
				// ...then filter null values and rebuild the token array
				$tokens = array_values(array_filter($tokens));
			}
		}
		$tokenCount = count($tokens);
		/*
		 * Apply plugins
		 */
		for ($i = 0; $i < $tokenCount; $i++) {
			$triggerToken = "|" . get_class($tokens[$i]) . "|";
			if (str_contains($globalTriggerTokens, $triggerToken)) {
				for ($ii = 0; $ii < $pluginCount; $ii++) {
					if ($pluginTriggerTokens[$ii] === false || str_contains($pluginTriggerTokens[$ii], $triggerToken)) {
						// Apply the plugin; if the return value is TRUE continue to the next token
						if ($plugins[$ii]->apply($tokens[$i]) === true) {
							continue 2;
						}
					}
				}
			}
		}
		// Stringify the tokens
		for ($i = 0; $i < $tokenCount; $i++) {
			$r .= (string)$tokens[$i];
		}
		$this->minified = $r;
		return $r;
	}
}
