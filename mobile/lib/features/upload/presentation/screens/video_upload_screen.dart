import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import 'package:livecommerce_mobile/core/l10n/app_localizations.dart';
import 'package:livecommerce_mobile/core/theme/app_colors.dart';
import 'package:livecommerce_mobile/core/theme/app_dimens.dart';
import 'package:livecommerce_mobile/features/upload/data/video_upload_repository.dart';
import 'package:livecommerce_mobile/features/upload/domain/entities/video_upload_status.dart';
import 'package:livecommerce_mobile/features/upload/presentation/providers/video_upload_providers.dart';
import 'package:livecommerce_mobile/features/upload/presentation/widgets/video_preview_controller.dart';
import 'package:livecommerce_mobile/shared/widgets/gradient_button.dart';
import 'package:video_player/video_player.dart';
import 'package:livecommerce_mobile/features/auth/presentation/providers/auth_providers.dart';
import 'package:livecommerce_mobile/features/upload/presentation/widgets/video_product_picker.dart';

class VideoUploadScreen extends ConsumerStatefulWidget {
  const VideoUploadScreen({super.key});

  @override
  ConsumerState<VideoUploadScreen> createState() => _VideoUploadScreenState();
}

class _VideoUploadScreenState extends ConsumerState<VideoUploadScreen> with WidgetsBindingObserver {
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  VideoPlayerController? _previewController;
  String _visibility = 'public';
  bool _picking = false;
  final Set<String> _productIds = {};

  Future<void> _chooseProducts() async {
    final result = await pickVideoProducts(context, ref, _productIds);
    if (result != null && mounted) setState(() { _productIds.clear(); _productIds.addAll(result); });
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    Future.microtask(() {
      if (mounted) ref.read(videoUploadNotifierProvider.notifier).checkStatus();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      ref.read(videoUploadNotifierProvider.notifier).checkStatus();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _titleController.dispose();
    _descriptionController.dispose();
    _previewController?.dispose();
    super.dispose();
  }

  String? _mimeFromPath(String name) {
    final ext = name.split('.').last.toLowerCase();
    return switch (ext) {
      'mp4' => 'video/mp4',
      'mov' => 'video/quicktime',
      'webm' => 'video/webm',
      _ => null,
    };
  }

  Future<void> _pickVideo() async {
    setState(() => _picking = true);
    final l10n = AppLocalizations.of(context)!;
    try {
      final picked = await ImagePicker().pickVideo(
        source: ImageSource.gallery,
        maxDuration: const Duration(seconds: 60),
      );
      if (picked == null || !mounted) {
        return;
      }

      final mime = picked.mimeType ?? _mimeFromPath(picked.name);
      final size = await picked.length();
      if (!mounted) return;

      if (mime == null ||
          (mime != 'video/mp4' &&
              mime != 'video/quicktime' &&
              mime != 'video/webm')) {
        _showMessage(l10n.uploadUnsupportedFormat);
        return;
      }
      if (size > VideoUploadRemoteDataSource.maxVideoBytes) {
        _showMessage(l10n.uploadTooLarge);
        return;
      }

      // The server can transcode MOV even when this browser cannot preview it.
      try {
        await _initPreview(picked.path);
      } catch (_) {
        await _previewController?.dispose();
        _previewController = null;
      }
      if (!mounted) {
        return;
      }
      ref.read(videoUploadNotifierProvider.notifier).selectVideo(
            path: picked.path,
            mimeType: mime,
            fileSize: size,
          );
    } catch (_) {
      if (mounted) {
        _showMessage(l10n.uploadPickFailed);
      }
    } finally {
      if (mounted) {
        setState(() => _picking = false);
      }
    }
  }

  Future<void> _initPreview(String path) async {
    final controller = createVideoPreview(path);
    try {
      await controller.initialize().timeout(const Duration(seconds: 15));
      await controller.setLooping(true);
      await controller.setVolume(0);
      await controller.play();
    } catch (_) {
      await controller.dispose();
      rethrow;
    }
    if (!mounted) {
      controller.dispose();
      return;
    }
    final old = _previewController;
    setState(() => _previewController = controller);
    await old?.dispose();
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  void _startUpload() {
    final duration = _previewController?.value.duration;
    if (duration != null && duration > const Duration(seconds: 60)) {
      _showMessage('Видео длиннее 60 секунд. Обрежьте ролик или выберите другой файл.');
      return;
    }
    ref.read(videoUploadNotifierProvider.notifier).upload(
          title: _titleController.text,
          description: _descriptionController.text,
          visibility: _visibility,
          productIds: _productIds.toList(),
        );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(videoUploadNotifierProvider);
    final l10n = AppLocalizations.of(context)!;

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.uploadTitle),
        leading: state.isBusy
            ? null
            : BackButton(
                onPressed: () {
                  if (state.stage == VideoUploadStage.failed ||
                      state.stage == VideoUploadStage.published) {
                    ref.read(videoUploadNotifierProvider.notifier).reset();
                  }
                  context.pop();
                },
              ),
      ),
      body: SafeArea(
        child: switch (state.stage) {
          VideoUploadStage.idle => _PickView(
              picking: _picking,
              onPick: _pickVideo,
            ),
          VideoUploadStage.picked => _FormView(
              productPicker: (ref.watch(authNotifierProvider).user?.isSeller ?? false)
                  ? OutlinedButton.icon(onPressed: _chooseProducts,
                      icon: const Icon(Icons.shopping_bag_outlined),
                      label: Text(_productIds.isEmpty ? 'Добавить товар на видео' : 'Товаров на видео: ${_productIds.length}'))
                  : null,
              titleController: _titleController,
              descriptionController: _descriptionController,
              visibility: _visibility,
              onVisibilityChanged: (value) =>
                  setState(() => _visibility = value),
              previewController: _previewController,
              error: state.error,
              onUpload: _startUpload,
            ),
          VideoUploadStage.uploading || VideoUploadStage.confirming =>
            _ProgressView(
              progress: state.progress,
              confirming: state.stage == VideoUploadStage.confirming,
            ),
          VideoUploadStage.processing => _ProcessingView(
              status: state.status,
              error: state.error,
              onCheck: () =>
                  ref.read(videoUploadNotifierProvider.notifier).checkStatus(),
            ),
          VideoUploadStage.published => _DoneView(
              status: state.status,
              onBackToFeed: () {
                ref.read(videoUploadNotifierProvider.notifier).reset();
                context.go('/home');
              },
              onUploadAnother: () {
                ref.read(videoUploadNotifierProvider.notifier).reset();
                _titleController.clear();
                _descriptionController.clear();
                _productIds.clear();
              },
            ),
          VideoUploadStage.failed => _FailedView(
              error: state.error,
              onRetry: _startUpload,
              onChooseAnother: () {
                ref.read(videoUploadNotifierProvider.notifier).reset();
                _titleController.clear();
                _descriptionController.clear();
                _productIds.clear();
              },
            ),
        },
      ),
    );
  }
}

class _PickView extends StatelessWidget {
  const _PickView({required this.picking, required this.onPick});

  final bool picking;
  final VoidCallback onPick;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);

    return Center(
      child: Padding(
        padding: AppSpacing.page,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 96,
              height: 96,
              decoration: BoxDecoration(
                gradient: AppGradients.brandDiagonal,
                borderRadius: AppRadius.xlAll,
                boxShadow: AppShadows.brandGlow(),
              ),
              child: const Icon(
                Icons.video_call_rounded,
                color: Colors.white,
                size: 44,
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            Text(
              l10n.uploadChooseVideo,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              'MP4 · MOV · WebM · 50 MB · 60 s',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: palette.textSecondary,
                  ),
            ),
            const SizedBox(height: AppSpacing.xxl),
            GradientButton(
              label: l10n.uploadPickFromGallery,
              icon: Icons.photo_library_outlined,
              busy: picking,
              onPressed: picking ? null : onPick,
            ),
          ],
        ),
      ),
    );
  }
}

class _FormView extends StatelessWidget {
  const _FormView({
    required this.titleController,
    required this.descriptionController,
    required this.visibility,
    required this.onVisibilityChanged,
    required this.previewController,
    required this.error,
    required this.onUpload,
    this.productPicker,
  });

  final TextEditingController titleController;
  final TextEditingController descriptionController;
  final String visibility;
  final ValueChanged<String> onVisibilityChanged;
  final VideoPlayerController? previewController;
  final String? error;
  final VoidCallback onUpload;
  final Widget? productPicker;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);

    return ListView(
      padding: AppSpacing.page,
      children: [
        if (previewController != null &&
            previewController!.value.isInitialized) ...[
          ClipRRect(
            borderRadius: AppRadius.lgAll,
            child: AspectRatio(
              aspectRatio: previewController!.value.aspectRatio,
              child: VideoPlayer(previewController!),
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
        ],
        TextField(
          controller: titleController,
          maxLength: 255,
          decoration: InputDecoration(
            labelText: l10n.uploadTitleLabel,
            border: const OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
        TextField(
          controller: descriptionController,
          maxLines: 3,
          maxLength: 2000,
          decoration: InputDecoration(
            labelText: l10n.uploadDescriptionLabel,
            alignLabelWithHint: true,
            border: const OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          l10n.uploadVisibilityLabel,
          style: Theme.of(context).textTheme.labelLarge?.copyWith(
                color: palette.textSecondary,
              ),
        ),
        const SizedBox(height: AppSpacing.sm),
        Wrap(
          spacing: AppSpacing.sm,
          children: [
            _VisibilityChip(
              label: l10n.visibilityPublic,
              selected: visibility == 'public',
              onTap: () => onVisibilityChanged('public'),
            ),
            _VisibilityChip(
              label: l10n.visibilityFollowers,
              selected: visibility == 'followers',
              onTap: () => onVisibilityChanged('followers'),
            ),
            _VisibilityChip(
              label: l10n.visibilityPrivate,
              selected: visibility == 'private',
              onTap: () => onVisibilityChanged('private'),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.xl),
        if (productPicker != null) ...[
          productPicker!, const SizedBox(height: AppSpacing.md),
        ],
        if (error != null) ...[
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(AppSpacing.md),
            decoration: BoxDecoration(
              color: palette.danger.withValues(alpha: 0.12),
              borderRadius: AppRadius.smAll,
            ),
            child: Text(
              error!,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: palette.danger,
                    fontWeight: FontWeight.w600,
                  ),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
        ],
        GradientButton(
          label: l10n.uploadButton,
          icon: Icons.cloud_upload_outlined,
          onPressed: onUpload,
        ),
      ],
    );
  }
}

class _VisibilityChip extends StatelessWidget {
  const _VisibilityChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final palette = AppPalette.of(context);
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
      selectedColor: palette.brand.withValues(alpha: 0.16),
      labelStyle: TextStyle(
        color: selected ? palette.brand : palette.textSecondary,
        fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
      ),
      side: BorderSide(color: selected ? palette.brand : palette.outline),
    );
  }
}

class _ProgressView extends StatelessWidget {
  const _ProgressView({required this.progress, required this.confirming});

  final double progress;
  final bool confirming;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);

    return Center(
      child: Padding(
        padding: AppSpacing.page,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (confirming)
              const CircularProgressIndicator()
            else ...[
              ClipRRect(
                borderRadius: AppRadius.lgAll,
                child: LinearProgressIndicator(
                  value: progress,
                  minHeight: 8,
                  backgroundColor: palette.surfaceHigh,
                  valueColor: const AlwaysStoppedAnimation(AppColors.brandPink),
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Text(
                '${(progress * 100).round()}%',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ],
            const SizedBox(height: AppSpacing.md),
            Text(
              confirming ? l10n.uploadConfirming : l10n.uploadUploading,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: palette.textSecondary,
                  ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ProcessingView extends StatelessWidget {
  const _ProcessingView({required this.onCheck, this.error, this.status});

  final VoidCallback onCheck;
  final String? error;
  final VideoUploadStatus? status;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);

    return Center(
      child: Padding(
        padding: AppSpacing.page,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircularProgressIndicator(),
            const SizedBox(height: AppSpacing.xl),
            Text(
              l10n.uploadProcessingTitle,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              error ?? 'Файл загружен. Подготовка видео к просмотру может занять несколько минут.',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: palette.textSecondary,
                  ),
            ),
            if ((status?.totalSteps ?? 0) > 0) ...[
              const SizedBox(height: AppSpacing.md),
              LinearProgressIndicator(value: status!.completedSteps / status!.totalSteps),
              const SizedBox(height: AppSpacing.sm),
              Text('Готово этапов: ${status!.completedSteps} из ${status!.totalSteps}'),
            ],
            const SizedBox(height: AppSpacing.xl),
            TextButton.icon(
              onPressed: onCheck,
              icon: const Icon(Icons.refresh_rounded),
              label: Text(l10n.uploadCheckStatus),
            ),
          ],
        ),
      ),
    );
  }
}

class _DoneView extends StatelessWidget {
  const _DoneView({
    required this.status,
    required this.onBackToFeed,
    required this.onUploadAnother,
  });

  final VideoUploadStatus? status;
  final VoidCallback onBackToFeed;
  final VoidCallback onUploadAnother;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);

    return Center(
      child: Padding(
        padding: AppSpacing.page,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (status?.thumbnailUrl != null &&
                status!.thumbnailUrl!.isNotEmpty) ...[
              ClipRRect(
                borderRadius: AppRadius.lgAll,
                child: Image.network(
                  status!.thumbnailUrl!,
                  height: 200,
                  width: 140,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
            ],
            const Icon(
              Icons.check_circle_rounded,
              size: 72,
              color: AppColors.success,
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(
              l10n.uploadDoneTitle,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: AppSpacing.sm),
            Text(
              l10n.uploadDoneHint,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: palette.textSecondary,
                  ),
            ),
            const SizedBox(height: AppSpacing.xxl),
            GradientButton(
              label: l10n.backToFeed,
              icon: Icons.play_circle_outline_rounded,
              onPressed: onBackToFeed,
            ),
            const SizedBox(height: AppSpacing.sm),
            TextButton(
              onPressed: onUploadAnother,
              child: Text(l10n.uploadAnotherVideo),
            ),
          ],
        ),
      ),
    );
  }
}

class _FailedView extends StatelessWidget {
  const _FailedView({
    required this.error,
    required this.onRetry,
    required this.onChooseAnother,
  });

  final String? error;
  final VoidCallback onRetry;
  final VoidCallback onChooseAnother;

  @override
  Widget build(BuildContext context) {
    final l10n = AppLocalizations.of(context)!;
    final palette = AppPalette.of(context);

    return Center(
      child: Padding(
        padding: AppSpacing.page,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.error_outline_rounded,
              size: 72,
              color: AppColors.danger,
            ),
            const SizedBox(height: AppSpacing.lg),
            Text(
              l10n.uploadFailedTitle,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            if (error != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                error!,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: palette.textSecondary,
                    ),
              ),
            ],
            const SizedBox(height: AppSpacing.xxl),
            GradientButton(
              label: l10n.uploadRetry,
              icon: Icons.refresh_rounded,
              onPressed: onRetry,
            ),
            const SizedBox(height: AppSpacing.sm),
            TextButton(
              onPressed: onChooseAnother,
              child: Text(l10n.uploadChooseAnother),
            ),
          ],
        ),
      ),
    );
  }
}
