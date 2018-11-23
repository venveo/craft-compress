# Compress plugin for Craft CMS 3.x

Compress exposes a variable within Twig to create zip archives from Asset queries as a native Craft asset itself.

## Features
- Compress asset query into a zip file
- Define a custom file name for the zip
- Compressed archives are stored as Assets themselves, so you may query them just like other assets
- Retrieve an asset query for the contents of an archive to show what files are contained in it
- Automatically forces zip files to be regenerated when a dependent asset is deleted in Craft

## Requirements

- Craft CMS 3.0.0-beta.23 or later.
- ext-zip PHP extension for creating zip files

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require venveo/compress

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Compress.

## Configuring Compress

1. Create a new volume in Craft to store your archives
2. Click "Settings" next to the plugin in the plugin list and select a storage volume for archives.

## Using Compress
### Example Usage
```twig
    {# Note: I didn't call ".all()" on this, we only want the query #}
    {% set assets = craft.assets.volume('images') %}
    
    {# Filename is optional, the zip extension will be appended automatically #}
    {% set archive = craft.compress.zip(assets, "my_file_name") %}
    
    {# the archive variable is now set to an Archive model #}
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
