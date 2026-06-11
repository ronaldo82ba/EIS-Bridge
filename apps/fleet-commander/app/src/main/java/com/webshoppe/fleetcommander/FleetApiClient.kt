package com.webshoppe.fleetcommander

import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.util.concurrent.TimeUnit

class FleetApiClient(private val config: CommanderConfig) {

    private val client = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(90, TimeUnit.SECONDS)
        .build()

    fun listAgents(): List<AgentSummary> {
        val request = Request.Builder()
            .url("${config.apiBaseUrl.trimEnd('/')}/fleet/agents")
            .header("X-Commander-Token", config.commanderToken)
            .get()
            .build()

        client.newCall(request).execute().use { response ->
            if (!response.isSuccessful) {
                throw IllegalStateException("Failed to list agents: HTTP ${response.code}")
            }

            val json = JSONObject(response.body?.string() ?: "{}")
            val data = json.optJSONArray("data") ?: JSONArray()

            return buildList {
                for (index in 0 until data.length()) {
                    val item = data.getJSONObject(index)
                    add(
                        AgentSummary(
                            agentId = item.getString("agent_id"),
                            deviceSerial = item.optString("device_serial"),
                            deviceModel = item.optString("device_model"),
                            status = item.optString("status"),
                            lastSeenAt = item.optString("last_seen_at"),
                        ),
                    )
                }
            }
        }
    }

    fun dispatch(command: String, targets: Any, payload: JSONObject = JSONObject()): JSONObject {
        val body = JSONObject()
            .put("command", command)
            .put("payload", payload)

        when (targets) {
            is String -> body.put("targets", targets)
            is List<*> -> body.put("targets", JSONArray(targets))
            else -> throw IllegalArgumentException("Unsupported targets type")
        }

        val request = Request.Builder()
            .url("${config.apiBaseUrl.trimEnd('/')}/fleet/tasks")
            .header("X-Commander-Token", config.commanderToken)
            .post(body.toString().toRequestBody("application/json".toMediaType()))
            .build()

        client.newCall(request).execute().use { response ->
            val text = response.body?.string() ?: "{}"
            if (!response.isSuccessful) {
                throw IllegalStateException("Task failed: HTTP ${response.code} $text")
            }
            return JSONObject(text)
        }
    }
}

data class AgentSummary(
    val agentId: String,
    val deviceSerial: String,
    val deviceModel: String,
    val status: String,
    val lastSeenAt: String,
)
