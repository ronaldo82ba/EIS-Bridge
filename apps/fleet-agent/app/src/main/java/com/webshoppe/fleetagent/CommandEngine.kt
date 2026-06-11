package com.webshoppe.fleetagent

import android.app.admin.DevicePolicyManager
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.content.pm.PackageInstaller
import android.os.BatteryManager
import android.os.Build
import android.os.Environment
import android.os.StatFs
import org.json.JSONObject
import java.io.BufferedReader
import java.io.File
import java.io.InputStreamReader
import java.util.concurrent.TimeUnit

class CommandEngine(private val context: Context) {

    private val dpm: DevicePolicyManager? =
        context.getSystemService(Context.DEVICE_POLICY_SERVICE) as? DevicePolicyManager

    private val adminComponent = ComponentName(context, DeviceAdminReceiver::class.java)

    fun execute(command: String, payload: JSONObject): JSONObject {
        return when (command) {
            "execute-shell" -> executeShell(payload.optString("command"))
            "reboot" -> reboot()
            "install-apk" -> installApk(payload.optString("path"))
            "clear-cache" -> clearCache(payload.optString("package"))
            "launch-app" -> launchApp(payload.optString("package"))
            "stop-app" -> stopApp(payload.optString("package"))
            "pull-logs" -> pullLogs(payload.optInt("lines", 200))
            "device-status" -> deviceStatus()
            else -> error("Unsupported command: $command")
        }
    }

    private fun executeShell(command: String): JSONObject {
        require(command.isNotBlank()) { "Shell command is required." }

        val process = Runtime.getRuntime().exec(arrayOf("sh", "-c", command))
        val stdout = process.inputStream.bufferedReader().use(BufferedReader::readText)
        val stderr = process.errorStream.bufferedReader().use(BufferedReader::readText)
        val exitCode = process.waitFor(60, TimeUnit.SECONDS)

        return JSONObject()
            .put("success", exitCode == 0)
            .put("exit_code", exitCode ?: -1)
            .put("stdout", stdout)
            .put("stderr", stderr)
    }

    private fun reboot(): JSONObject {
        if (dpm?.isDeviceOwnerApp(context.packageName) == true) {
            dpm.reboot(adminComponent)
            return JSONObject().put("success", true).put("message", "Reboot initiated.")
        }

        Runtime.getRuntime().exec(arrayOf("sh", "-c", "svc power reboot"))
        return JSONObject().put("success", true).put("message", "Reboot requested via shell.")
    }

    private fun installApk(path: String): JSONObject {
        require(path.isNotBlank()) { "APK path is required." }

        val file = File(path)
        require(file.exists()) { "APK not found: $path" }

        val args = arrayOf("pm", "install", "-r", "-d", file.absolutePath)
        val process = Runtime.getRuntime().exec(args)
        val output = process.inputStream.bufferedReader().use(BufferedReader::readText)
        val exitCode = process.waitFor(120, TimeUnit.SECONDS)

        return JSONObject()
            .put("success", exitCode == 0 && output.contains("Success", ignoreCase = true))
            .put("output", output.trim())
    }

    private fun clearCache(packageName: String): JSONObject {
        require(packageName.isNotBlank()) { "Package name is required." }

        val process = Runtime.getRuntime().exec(arrayOf("pm", "clear", packageName))
        val output = process.inputStream.bufferedReader().use(BufferedReader::readText)
        val exitCode = process.waitFor(30, TimeUnit.SECONDS)

        return JSONObject()
            .put("success", exitCode == 0)
            .put("output", output.trim())
    }

    private fun launchApp(packageName: String): JSONObject {
        require(packageName.isNotBlank()) { "Package name is required." }

        val launchIntent = context.packageManager.getLaunchIntentForPackage(packageName)
            ?: throw IllegalArgumentException("No launch intent for $packageName")

        launchIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        context.startActivity(launchIntent)

        return JSONObject().put("success", true).put("package", packageName)
    }

    private fun stopApp(packageName: String): JSONObject {
        require(packageName.isNotBlank()) { "Package name is required." }

        val process = Runtime.getRuntime().exec(arrayOf("am", "force-stop", packageName))
        val exitCode = process.waitFor(15, TimeUnit.SECONDS)

        return JSONObject()
            .put("success", exitCode == 0)
            .put("package", packageName)
    }

    private fun pullLogs(lines: Int): JSONObject {
        val process = Runtime.getRuntime().exec(arrayOf("logcat", "-d", "-t", lines.toString()))
        val output = process.inputStream.bufferedReader().use(BufferedReader::readText)
        process.waitFor(30, TimeUnit.SECONDS)

        return JSONObject()
            .put("success", true)
            .put("lines", lines)
            .put("log", output)
    }

    private fun deviceStatus(): JSONObject {
        val battery = context.registerReceiver(null, IntentFilter(Intent.ACTION_BATTERY_CHANGED))
        val level = battery?.getIntExtra(BatteryManager.EXTRA_LEVEL, -1) ?: -1
        val scale = battery?.getIntExtra(BatteryManager.EXTRA_SCALE, -1) ?: -1
        val batteryPct = if (scale > 0) (level * 100 / scale) else -1

        val stat = StatFs(Environment.getDataDirectory().path)
        val freeBytes = stat.availableBlocksLong * stat.blockSizeLong
        val totalBytes = stat.blockCountLong * stat.blockSizeLong

        return JSONObject()
            .put("success", true)
            .put("agent_id", Build.SERIAL)
            .put("model", Build.MODEL)
            .put("android_version", Build.VERSION.RELEASE)
            .put("battery_pct", batteryPct)
            .put("storage_free_bytes", freeBytes)
            .put("storage_total_bytes", totalBytes)
            .put("uptime_ms", android.os.SystemClock.elapsedRealtime())
    }

    private fun error(message: String): JSONObject {
        return JSONObject().put("success", false).put("error", message)
    }
}
