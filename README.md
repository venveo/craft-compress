# Compress plugin for Craft CMS 4.x

Compress exposes a variable within Twig to create zip archives from Asset queries as a native Craft asset itself.

## Features
- Compress asset query into a zip file
- Generate "lazy links": will dispatch a queue job to generate archives,
if a user clicks a link before the job is completed or started, the 
asset will be fetched on-demand and the queue job cancelled.
- Compressed archives are stored as Assets themselves, so you may query 
them just like other assets.
- Retrieve an asset query for the contents of an archive to show what 
files are contained in it.
- Automatically forces zip files to be regenerated when a dependent 
asset is deleted or updated in Craft.

## Requirements

- Craft CMS 4.0.0 or later.
- ext-zip PHP extension for creating zip files (also conveniently a Craft requirement)

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require venveo/craft-compress

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Compress.

## Configuring Compress

1. Compress creates its archives as assets in Craft, so you'll need a 
place to put them. Create a new volume.
2. Click "Settings" next to the plugin in the plugin list and select a 
storage volume for archives. It's not recommended to use an existing
volume. The volume must have URLs in order for the Twig helper to return
download links; however, it will function without it and respect your
settings and life choices.

## Using Compress

### Basic Example
```twig
    {# Feel free to provide an array of assets if you prefer! #}
    {% set assets = craft.assets.volume('images') %}
    
    {# 
    Second parameter is whether we want the archive generated on page 
    load or lazily.
    #}
    {% set archive = craft.compress.zip(assets, true, 'My Photos') %}
    
    {# 
    the archive variable is now set to an Archive model, but since 
    we're in lazy mode, the getAsset() response may be null. We can
    either check the .isReady method or we can just get the lazyLink, which will give us an indirect link to the asset.
    #}
    {% if archive.isReady %}
        {% set archiveAsset = archive.getAsset() %}
        <a href="{{ archiveAsset.getUrl() }}">Download All Files</a>
        {% else %}
        <a href="{{ archive.lazyLink}}">Download All Files</a>
    {% endif %}
    
    {# We can get a new AssetQuery with the contents of the archive! #}
    {% set contents = archiveAsset.getContents().all() %}
    <ul>
    {% for file in contents %}
        <li><a href="{{ file.getUrl() }}">{{ file.title }}</a></li>
    {% endfor %}
    </ul>
```

### Advanced Example
This example gets all assets, groups them by the file type, and then
generates a lazy link to download all assets of a particular kind.

```twig
{% set assets = craft.assets({
    volume: 'local'
}).all() %}

{% for kind, assetGroup in assets|group(a => a.kind) %}
    {% set archive = craft.compress.zip(assetGroup, true) %}
    <strong>{{ kind }} - <a href="{{ archive.lazyLink }}">Download All ({{ archive.isReady ? 'Ready' : 'Not ready' }})</a></strong>
    {% for asset in assetGroup %}
        <li>{{ asset.filename }}</li>
    {% endfor %}
{% endfor %}
```

## Caveats & Limitations
- Consider the Assets created by Compress to be temporary. Don't try
to use them in Asset relation fields.
set changes, a new archive asset will be created and the prior will not
be automatically deleted.
- When you provide a name for your archive, it's a good idea to ensure that name is unique to the files you're zipping up. Failure to do so could result in the file not being cached well and being constantly overwritten. 

Brought to you by [Venveo](https://www.venveo.com)
