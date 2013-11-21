# wp-oam-renderer

Wordpress plugin page: http://wordpress.org/plugins/wp-edge-animate-renderer-oam/

Allows and renders OAM ([Adobe Edge Animate](http://html.adobe.com/fr/edge/animate/)) files in your [Wordpress](http://wordpress.org/) posts.

## How does it work?

* Allows upload for OAM files in Wordpress
* Unzips the `my_file.oam` in `my_file/` folder at the same location
* Provides automatic server-side iframe rendering for `<a href="http://site.com/wp-content/upload/2013/03/my_file.oam"></a>`
* Provides `[oam]` shortcode for manuel embed
* Deletes `my_file/` folder when `my_file.oam` is deleted from the library

## Requirements

* jQuery for automatic iframe rendering

## Installation

* Get the source from GIT or Wordpress SVN repository (soon).
* Go to Admin > Plugins
* Activate wp-oam-renderer

## Plugin documentation

### Automatic iframe rendering

#### Usage

Simply insert your oam in your content from the media library using the "ATTACHMENT DISPLAY SETTINGS" name "Media File". 
It will generate a link to the OAM file, with will be rendered via jQuery as an iframe.

#### Known limitations

* At this time, iframe dimension is not manageable.

### [oam] shortcode

#### Parameters

* id *(mandatory)*
* width *(optional)*
* height *(optional)*

#### Usage

##### Getting size from animation:

`[oam id="28"]`

##### Providing `width`, `height` will be calculated proportionaly

`[oam id="28" width="960"]`

##### Providing both `width` and `height`

`[oam id="28" width="960" heigh="540"]`

#### Known limitations

* Getting the media id is not really the best experience... but works!

## Contribute

* Fork
* Add value
* Pull Request
* Enjoy

