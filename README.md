# Scrobbble
Scrobble listening data to WordPress, directly.

This plugin implements Last.fm's v1.2 scrobbling API, allowing media players to submit listening data straight to your WordPress site.

It registers a new "Listen" Custom Post Type, and a number of filter (and action) hooks. Here's some examples of further customizations:
```
add_filter( 'scrobbble_title', function( $title ) {
  return ucwords( $title );
} );
```
Other such filters are `scrobbble_artist`, and `scrobbble_album`.

```
add_filter( 'scrobbble_skip_track', function( $skip, $data ) {
  if ( $data['artist'] === 'Journey' ) {
    return true; // Prevent any such track from being imported.
  }

  return $skip;
}, 10, 2 );
```
```
add_filter( 'scrobbble_content', function( $content, $data ) {
  // This is where you could completely alter Listens' markup.
  return $content;
}, 10, 2 );
```
```
add_action( 'scrobbble_save_track', function( $post_id, $data ) {
  // Runs after a Listen with post ID `$post_id` was first inserted in the
  // database. Use it to, e.g., call Discogs' API and attach additional metadata
  // to the post.
}, 10, 2 );
```

## Why the Three B's?
Remember when Dan Cederholm created Dribbble and it was super cool? 'Cause we do.

## I use MPD, How Do I Get This to Work?
Install mpdscribble, and in `/etc/mpdscribbble.conf`, under `#[libre.fm]`, add your WordPress username, your password of choice, and your site's Scrobbble (e.g., `https://www.example.org/wp-json/scrobbble/v1/scrobbble`) endpoint.

## I use foobar2000, How Do I Get This to Work?
(This is extremely niche, but) you'll probably need to install [this 10-year-old plugin](https://www.foobar2000.org/components/view/foo_audioscrobbler), but not before you've used a hex editor to modify its baked-in scrobbling endpoint.

Then, add your credentials to File > Preferences > Tools > Audioscrobbler.

## Do I Need a Last.fm or Libre.fm Account?
No. This plugin aims to fully replace those services.

## Uh, What's My Username and Password?
Your username would be your WordPress username. Your password can be anything, as long as it's defined in `wp-config.php` like so:
```
define( 'SCROBBBLE_PASS', 'your-password-of-choice' ); // Add this password to your media player's config, too.
```
(Tip: Make it long, hard to guess, and different from your WordPress password.)

As that's this plugin's only "setting," there is no Settings page or anything.

## What's With the Custom Taxonomies?
They're there, but not actually used. I added them after I had a look at [Playlist Log](https://wordpress.org/plugins/playlistlog/), a similar WordPress plugin, but haven't gotten around to them, yet.

## The Audioscrobbler Protocol Is Plain Text, Though
I just really like how, in WordPress, you can define "JSON API" routes and everything just works. Even non-JSON stuff.
