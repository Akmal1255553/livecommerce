allprojects {
    repositories {
        // Local preseed (Gradle TLS to Google sometimes fails behind VPN/AV).
        val localMaven = file("D:/dev-cache/m2/repository")
        if (localMaven.isDirectory) maven { url = localMaven.toURI() }
        google()
        mavenCentral()
    }
}

// Plugins like agora_rtc_engine use safeExtGet('compileSdkVersion', 31).
// Raise the shared ext so AAR metadata checks pass against current AndroidX.
extra["compileSdkVersion"] = 36
extra["targetSdkVersion"] = 36
extra["minSdkVersion"] = 24

val newBuildDir: Directory =
    rootProject.layout.buildDirectory
        .dir("../../build")
        .get()
rootProject.layout.buildDirectory.value(newBuildDir)

subprojects {
    val newSubprojectBuildDir: Directory = newBuildDir.dir(project.name)
    project.layout.buildDirectory.value(newSubprojectBuildDir)
}

// Ensure Android library/app modules see required AndroidX APIs (LifecycleOwner, etc.).
subprojects {
    pluginManager.withPlugin("com.android.library") {
        // Align explicit plugin SDKs after their DSL is configured and before
        // Android creates variants and dependency metadata checks.
        extensions.configure<com.android.build.api.variant.LibraryAndroidComponentsExtension> {
            finalizeDsl { android ->
                android.compileSdk = maxOf(android.compileSdk ?: 36, 36)
            }
        }
        dependencies {
            add("implementation", "androidx.annotation:annotation:1.8.2")
            add("implementation", "androidx.fragment:fragment:1.7.1")
            add("implementation", "androidx.appcompat:appcompat:1.7.0")
            add("implementation", "androidx.lifecycle:lifecycle-runtime:2.7.0")
            add("implementation", "androidx.lifecycle:lifecycle-common:2.7.0")
            add("implementation", "androidx.activity:activity:1.8.1")
        }
    }
    pluginManager.withPlugin("com.android.application") {
        dependencies {
            add("implementation", "androidx.annotation:annotation:1.8.2")
            add("implementation", "androidx.fragment:fragment:1.7.1")
            add("implementation", "androidx.appcompat:appcompat:1.7.0")
            add("implementation", "androidx.lifecycle:lifecycle-runtime:2.7.0")
            add("implementation", "androidx.lifecycle:lifecycle-common:2.7.0")
            add("implementation", "androidx.activity:activity:1.8.1")
        }
    }
}

subprojects {
    project.evaluationDependsOn(":app")
}

// Kotlin 2.3+ rejects languageVersion 1.6 used by some plugins (sentry_flutter).
subprojects {
    tasks.withType<org.jetbrains.kotlin.gradle.tasks.KotlinCompile>().configureEach {
        compilerOptions {
            languageVersion.set(org.jetbrains.kotlin.gradle.dsl.KotlinVersion.KOTLIN_2_0)
            apiVersion.set(org.jetbrains.kotlin.gradle.dsl.KotlinVersion.KOTLIN_2_0)
        }
    }
}

tasks.register<Delete>("clean") {
    delete(rootProject.layout.buildDirectory)
}
