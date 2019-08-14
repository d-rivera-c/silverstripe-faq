# Configuring Solr
The module assumes you already have Solr installed and configured. If using CWP, then it will be configured for you
so you only need to worry about [adding a few tweaks](cwp.md).
We assume that if you are using this module, you already have Solr configured an running,
but if you need help getting started with configuration, here is some starting code to get you started.

```php
/**
 * Configure Solr.
 */
if(!class_exists('Solr')) return;

// get options from configuration
$options = Config::inst()->get('CwpSolr', 'options');
$solrOptions = array(
			'host' => defined('SOLR_SERVER') ? SOLR_SERVER : 'localhost',
			'port' => defined('SOLR_PORT') ? SOLR_PORT : 8983,
			'path' => defined('SOLR_PATH') ? SOLR_PATH : '/solr/',
			'version' => 4,

			'indexstore' => array(
				'mode' => defined('SOLR_MODE') ? SOLR_MODE : 'file',
				'auth' => defined('SOLR_AUTH') ? SOLR_AUTH : NULL,

				// Allow storing the solr index and config data in an arbitrary location,
				// e.g. outside of the webroot
				'path' => defined('SOLR_INDEXSTORE_PATH') ? SOLR_INDEXSTORE_PATH : BASE_PATH . '/.solr',
				'remotepath' => defined('SOLR_REMOTE_PATH') ? SOLR_REMOTE_PATH : null
			)
		);

Solr::configure_server($solrOptions);
```

This code would go on `mysite/_config.php`.

