import 'dart:io';
import 'package:video_player/video_player.dart';

VideoPlayerController createVideoPreview(String path) =>
    VideoPlayerController.file(File(path));
