package com.webshoppe.fleetagent

import android.app.Application

class FleetAgentApplication : Application() {
    var config: AgentConfig = AgentConfig("", "", "", 8765, 15)
        private set

    override fun onCreate() {
        super.onCreate()
        config = AgentConfig.load(this)
    }

    fun updateConfig(value: AgentConfig) {
        config = value
    }
}

data class AgentConfig(
    val agentId: String,
    val apiBaseUrl: String,
    val agentToken: String,
    val httpPort: Int,
    val pollIntervalSec: Int,
) {
    companion object {
        fun load(app: Application): AgentConfig {
            val prefs = app.getSharedPreferences("fleet_agent", Application.MODE_PRIVATE)
            return AgentConfig(
                agentId = prefs.getString("agent_id", android.os.Build.SERIAL) ?: "unknown-agent",
                apiBaseUrl = prefs.getString("api_base_url", "https://sandbox.eisbridge.com/v1") ?: "",
                agentToken = prefs.getString("agent_token", "") ?: "",
                httpPort = prefs.getInt("http_port", 8765),
                pollIntervalSec = prefs.getInt("poll_interval_sec", 15),
            )
        }

        fun save(app: Application, config: AgentConfig) {
            app.getSharedPreferences("fleet_agent", Application.MODE_PRIVATE)
                .edit()
                .putString("agent_id", config.agentId)
                .putString("api_base_url", config.apiBaseUrl)
                .putString("agent_token", config.agentToken)
                .putInt("http_port", config.httpPort)
                .putInt("poll_interval_sec", config.pollIntervalSec)
                .apply()
        }
    }
}
