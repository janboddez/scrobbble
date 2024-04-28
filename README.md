# Scrobbble
Scrobble listening data to WordPress, directly.

This plugin _partially_ implements Last.fm's API, allowing media players to submit (or "scrobble") listening data straight to your WordPress site.

Supported clients:
- mpdscribble (Linux): uses the v1.2 API to submit "scrobbles" as well as "now playing" tracks.
- foobar2000 (Windows): can be set up to use the v1.2 API to submit "scrobbles" as well as "now playing" tracks.
- Pano Scrobbler (Android): uses the v2.0 API to submit "scrobbles" as well as "now playing" tracks; other methods are currently not implemented.

It registers a new "listen" custom post type, a "Now Playing" block, and a number of filter (and action) hooks.

## Hooks
Here's some examples of further customizations.

### Filter Title, Artist, and Album Information
```
add_filter( 'scrobbble_title', function( $title ) {
  return ucwords( $title );
} );
```
Other such filters are `scrobbble_artist`, and `scrobbble_album`.

### Skip Specific Scrobbles
```
add_filter( 'scrobbble_skip_track', function( $skip, $data ) {
  if ( $data['artist'] === 'Journey' ) {
    return true; // Prevent any such track from being imported.
  }

  return $skip;
}, 10, 2 );
```

### Modify "Listens"
```
add_filter( 'scrobbble_content', function( $content, $data ) {
  // This is where you could completely alter Listens' markup.
  return $content;
}, 10, 2 );
```

### Run Additional Functions After a Listen Is Created
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
Install [mpdscribble](https://www.musicpd.org/clients/mpdscribble/), and in `/etc/mpdscribbble.conf` (or `~/.mpdscribble/mpdscribble.conf`), under `[libre.fm]`, add your WordPress username, your password of choice, and your site's Scrobbble (e.g., `https://www.example.org/wp-json/scrobbble/v1/scrobbble`) endpoint.

## I use foobar2000, How Do I Get This to Work?
(This is extremely niche, but) you'll probably need to install [this 10-year-old plugin](https://www.foobar2000.org/components/view/foo_audioscrobbler), but not before you've used a hex editor to modify its baked-in scrobbling endpoint. [This Reddit post](https://web.archive.org/web/20180522184216/https://www.reddit.com/r/foobar2000/comments/3zaiy6/guide_to_librefm_scrobbling_lastfm_backup_to/) explains how to do that.

(My endpoint, turns out, was too long, and I set up a shorter URL which redirects to it to work around that limitation. If this happens to be the case for you, too: only the main `/wp-json/scrobbble/v1/scrobbble` endpoint needs this redirect.)

Then, add your credentials to File > Preferences > Tools > Audioscrobbler.

## I use Pano Scrobbler, How Do I Get This to Work?
You'll want to enable scrobbling to "GNU FM" (GNU FM being the software behind Libre.fm).

For "API URL," enter, e.g., `https://www.example.org/wp-json/scrobbble/v1/scrobbble/2.0`. Sign in with your WordPress login and the unique password you set in `wp-config.php`—see below.

You won't see things like recent tracks or top artists—you're more likely to see a bunch of "Invalid Method" popups, haha—but scrobbling and "now playing" should work fine.

## Do I Need a Last.fm or Libre.fm Account?
No. This plugin fully replaces those services.

## Uh, What's My Username and Password?
Your username would be your **WordPress username**. Your password can be **anything**, as long as it's defined in `wp-config.php` like so:
```
define( 'SCROBBBLE_PASS', 'your-password-of-choice' ); // Add this password to your media player's config, too.
```
(Tip: Make it long, hard to guess, and different from your WordPress password.)

As that's this plugin's only "setting," there is no Settings page or anything.

### Multi-User Support
If your WordPress site has multiple autors (or is a multisite install), you can define one password per user, like so:
```
// Suppose your user name (login) is "alice."
define( 'SCROBBBLE_PASS_ALICE, 'your-super-unique-password' );
// Or "josh" ...
define( 'SCROBBBLE_PASS_JOSH, 'another-password' );
```
Again, do not use your actual WordPress password but something unique (and make sure it's set the same in your audio player/scrobbler, of course).

## What's With the Custom Taxonomies?
They're there, but not actually used. I added them after I had a look at [Playlist Log](https://wordpress.org/plugins/playlistlog/), a similar WordPress plugin, but haven't gotten around to them, yet.

## The Audioscrobbler Protocol Is Plain Text, Though
I just really like how, in WordPress, you can define "JSON API" routes and everything just works. Even non-JSON stuff.
