# Compress plugin for Craft CMS 3.x

Compress exposes a variable within Twig to create zip archives from Asset queries as a native Craft asset itself.

## Features
- Compress asset query into a zip file
- Generate "lazy links": will dispatch a queue job to generate archives, if a user clicks a link before the job is completed or started, the asset will be fetched on-demand, or define your own logic.
- Compressed archives are stored as Assets themselves, so you may query them just like other assets
- Retrieve an asset query for the contents of an archive to show what files are contained in it
- Automatically forces zip files to be regenerated when a dependent asset is deleted in Craft

## Requirements

- Craft CMS 3.2.0 or later.
- ext-zip PHP extension for creating zip files (also conveniently a Craft requirement)

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require venveo/compress

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Compress.

## Configuring Compress

1. Compress creates its archives as assets in Craft, so you'll need a 
place to put them. Create a new volume.
2. Click "Settings" next to the plugin in the plugin list and select a 
storage volume for archives. It's not recommended to use an existing
volume.

## Using Compress
### Example Usage
```twig
    {# Note: I didn't call ".all()" on this, we only want the query #}
    {% set assets = craft.assets.volume('images') %}
    
    {# Second parameter is whether we want the archive generated on page load or lazily #}
    {% set archive = craft.compress.zip(assets, true) %}
    
    {# the archive variable is now set to an Archive model, though since we're in lazy mode, the getAsset() response may be null #}
    {% set archiveAsset = archive.getAsset() %}
    <a href="{{ archiveAsset.getUrl() }}">Download All Files</a>
    
    {# We can get a new AssetQuery with the contents of the archive #}
    {% set contents = archiveAsset.getContents().all() %}
    <ul>
    {% for file in contents %}
        <li><a href="{{ file.getUrl() }}">{{ file.title }}</a></li>
    {% endfor %}
    </ul>
```


Brought to you by [Venveo](https://venveo.com)
