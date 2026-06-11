package com.webshoppe.fleetagent

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.Service
import android.content.Intent
import android.os.IBinder
import androidx.core.app.NotificationCompat
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject
import java.util.concurrent.TimeUnit

class FleetPollService : Service() {

    private val client = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(60, TimeUnit.SECONDS)
        .build()

    private lateinit var config: AgentConfig
    private lateinit var engine: CommandEngine
    private var running = false

    override fun onCreate() {
        super.onCreate()
        config = (application as FleetAgentApplication).config
        engine = CommandEngine(this)
        startForeground(NOTIFICATION_ID, buildNotification("Fleet Agent running"))
    }

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        if (!running) {
            running = true
            Thread { pollLoop() }.start()
        }
        return START_STICKY
    }

    override fun onBind(intent: Intent?): IBinder? = null

    private fun pollLoop() {
        while (running) {
            try {
                sendHeartbeat()
                pollPendingTasks()
            } catch (_: Exception) {
            }
            Thread.sleep(config.pollIntervalSec * 1000L)
        }
    }

    private fun sendHeartbeat() {
        if (config.agentToken.isBlank()) return

        val status = engine.execute("device-status", JSONObject())
        val body = JSONObject()
            .put("status", status)
            .put("callback_base_url", "http://127.0.0.1:${config.httpPort}")
            .toString()
            .toRequestBody("application/json".toMediaType())

        val request = Request.Builder()
            .url("${config.apiBaseUrl.trimEnd('/')}/fleet/agents/heartbeat")
            .header("X-Agent-Token", config.agentToken)
            .post(body)
            .build()

        client.newCall(request).execute().close()
    }

    private fun pollPendingTasks() {
        if (config.agentToken.isBlank()) return

        val request = Request.Builder()
            .url("${config.apiBaseUrl.trimEnd('/')}/fleet/agents/me/pending-tasks")
            .header("X-Agent-Token", config.agentToken)
            .get()
            .build()

        client.newCall(request).execute().use { response ->
            if (!response.isSuccessful) return

            val json = JSONObject(response.body?.string() ?: return)
            val tasks = json.optJSONArray("data") ?: return

            for (index in 0 until tasks.length()) {
                val task = tasks.getJSONObject(index)
                executeAndReport(task)
            }
        }
    }

    private fun executeAndReport(task: JSONObject) {
        val resultId = task.getString("result_id")
        val command = task.getString("command")
        val payload = task.optJSONObject("payload") ?: JSONObject()

        val response = try {
            engine.execute(command, payload)
        } catch (exception: Exception) {
            JSONObject().put("success", false).put("error", exception.message)
        }

        val success = response.optBoolean("success", false)
        val report = JSONObject()
            .put("success", success)
            .put("response", response)
            .put("error", if (success) JSONObject.NULL else response.optString("error"))
            .toString()
            .toRequestBody("application/json".toMediaType())

        val request = Request.Builder()
            .url("${config.apiBaseUrl.trimEnd('/')}/fleet/agents/me/task-results/$resultId")
            .header("X-Agent-Token", config.agentToken)
            .post(report)
            .build()

        client.newCall(request).execute().close()
    }

    private fun buildNotification(text: String): Notification {
        val channelId = "fleet_agent"
        val manager = getSystemService(NotificationManager::class.java)
        manager.createNotificationChannel(
            NotificationChannel(channelId, "Fleet Agent", NotificationManager.IMPORTANCE_LOW),
        )

        return NotificationCompat.Builder(this, channelId)
            .setContentTitle("Fleet Agent")
            .setContentText(text)
            .setSmallIcon(android.R.drawable.ic_menu_manage)
            .setOngoing(true)
            .build()
    }

    companion object {
        private const val NOTIFICATION_ID = 1001
    }
}
