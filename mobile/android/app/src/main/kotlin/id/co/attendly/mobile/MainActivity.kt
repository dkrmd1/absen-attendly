package id.co.attendly.mobile

import com.google.android.play.core.integrity.IntegrityManagerFactory
import com.google.android.play.core.integrity.StandardIntegrityManager
import com.google.android.play.core.integrity.StandardIntegrityManager.PrepareIntegrityTokenRequest
import com.google.android.play.core.integrity.StandardIntegrityManager.StandardIntegrityTokenRequest
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.embedding.android.FlutterActivity
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private val channelName = "id.co.attendly.mobile/play_integrity"
    private var tokenProvider: StandardIntegrityManager.StandardIntegrityTokenProvider? = null

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, channelName)
            .setMethodCallHandler { call, result ->
                if (call.method != "requestToken") {
                    result.notImplemented()
                    return@setMethodCallHandler
                }

                val projectNumber = call.argument<String>("cloudProjectNumber")
                    ?.toLongOrNull()
                val requestHash = call.argument<String>("requestHash")
                if (projectNumber == null || requestHash.isNullOrBlank()) {
                    result.error("INVALID_ARGUMENT", "Play Integrity configuration is invalid.", null)
                    return@setMethodCallHandler
                }

                requestIntegrityToken(projectNumber, requestHash, result)
            }
    }

    private fun requestIntegrityToken(
        projectNumber: Long,
        requestHash: String,
        result: MethodChannel.Result,
    ) {
        val manager = IntegrityManagerFactory.createStandard(applicationContext)
        val provider = tokenProvider
        if (provider != null) {
            requestToken(provider, requestHash, result)
            return
        }

        manager.prepareIntegrityToken(
            PrepareIntegrityTokenRequest.builder()
                .setCloudProjectNumber(projectNumber)
                .build(),
        ).addOnSuccessListener { preparedProvider ->
            tokenProvider = preparedProvider
            requestToken(preparedProvider, requestHash, result)
        }.addOnFailureListener { error ->
            result.error("PREPARE_FAILED", "Play Integrity could not prepare a token.", error.message)
        }
    }

    private fun requestToken(
        provider: StandardIntegrityManager.StandardIntegrityTokenProvider,
        requestHash: String,
        result: MethodChannel.Result,
    ) {
        provider.request(
            StandardIntegrityTokenRequest.builder()
                .setRequestHash(requestHash)
                .build(),
        ).addOnSuccessListener { response ->
            result.success(response.token())
        }.addOnFailureListener { error ->
            result.error("TOKEN_FAILED", "Play Integrity could not create a token.", error.message)
        }
    }
}
