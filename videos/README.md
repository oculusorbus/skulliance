Music videos for musicvideos.php.

Drop `.mp4` files here, named after the project: `discosolaris.mp4`,
`heistonalpha.mp4`. The page picks up whatever is in this folder, matches each
file to a project by that name (or by the project's currency), and orders them
alphabetically. Nothing needs to be added to a list.

A file whose name matches neither is still shown, titled from the filename --
so if one appears with a filename under it instead of the artist's logo, add a
line to MV_ALIASES in lib/music-videos.php. There is one there already:
projectvenus.mp4 is f.ART's.

This README is the only committed file here; the videos themselves are
deployed by FTP like every other piece of media on this platform.
