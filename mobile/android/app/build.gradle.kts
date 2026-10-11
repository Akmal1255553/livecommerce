plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "uz.livecommerce.livecommerce_mobile"
    // Plugins (e.g. agora_rtc_engine) require compileSdk >= 34 for current AndroidX.
    compileSdk = maxOf(flutter.compileSdkVersion, 36)
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "uz.livecommerce.livecommerce_mobile"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
        // Installable phone APKs include both common ARM architectures.
        ndk {
            abiFilters += listOf("arm64-v8a", "armeabi-v7a")
        }
    }

    signingConfigs {
        val demoKeystore = System.getenv("DEMO_KEYSTORE_PATH")
        if (!demoKeystore.isNullOrBlank()) {
            create("demo") {
                storeFile = file(demoKeystore)
                storePassword = System.getenv("DEMO_KEYSTORE_PASSWORD")
                keyAlias = "livecommerce-demo"
                keyPassword = System.getenv("DEMO_KEYSTORE_PASSWORD")
            }
        }
    }

    buildTypes {
        release {
            // TODO: Add your own signing config for the release build.
            // Signing with the debug keys for now, so `flutter run --release` works.
            signingConfig = signingConfigs.findByName("demo") ?: signingConfigs.getByName("debug")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}

dependencies {
    // FlutterActivity → LifecycleOwner; needed when plugin classpath doesn't expose it.
    implementation("androidx.lifecycle:lifecycle-runtime:2.7.0")
    implementation("androidx.lifecycle:lifecycle-common:2.7.0")
    implementation("androidx.activity:activity:1.8.1")
    implementation("androidx.fragment:fragment:1.7.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.annotation:annotation:1.8.2")
}
