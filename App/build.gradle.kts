dependencies {
    // Retrofit for networking
    implementation("com.squareup.retrofit2:retrofit:2.9.0") // Use latest version
    // Gson converter for JSON parsing with Retrofit
    implementation("com.squareup.retrofit2:converter-gson:2.9.0")
    // Coroutines for asynchronous operations
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.7.3") // Use latest version
    implementation("androidx.lifecycle:lifecycle-viewmodel-ktx:2.7.0") // For ViewModelScope
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.7.0") // For lifecycleScope

    // Standard Android dependencies...
    implementation("androidx.core:core-ktx:1.12.0")
    implementation("androidx.appcompat:appcompat:1.6.1")
    implementation("com.google.android.material:material:1.11.0")
    implementation("androidx.constraintlayout:constraintlayout:2.1.4")
    // Add RecyclerView if not already present
    implementation("androidx.recyclerview:recyclerview:1.3.2")
}