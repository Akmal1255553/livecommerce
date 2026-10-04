import 'package:video_player/video_player.dart';

VideoPlayerController createVideoPreview(String path) =>
    VideoPlayerController.networkUrl(Uri.parse(path));
