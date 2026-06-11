package com.webshoppe.fleetcommander

import android.app.Application

class CommanderApplication : Application() {
    var config: CommanderConfig = CommanderConfig.load(this)
        private set

    fun updateConfig(value: CommanderConfig) {
        config = value
        CommanderConfig.save(this, value)
    }
}

data class CommanderConfig(
    val apiBaseUrl: String,
    val commanderToken: String,
) {
    companion object {
        fun load(app: Application): CommanderConfig {
            val prefs = app.getSharedPreferences("fleet_commander", Application.MODE_PRIVATE)
            return CommanderConfig(
                apiBaseUrl = prefs.getString("api_base_url", "https://sandbox.eisbridge.com/v1") ?: "",
                commanderToken = prefs.getString("commander_token", "") ?: "",
            )
        }

        fun save(app: Application, config: CommanderConfig) {
            app.getSharedPreferences("fleet_commander", Application.MODE_PRIVATE)
                .edit()
                .putString("api_base_url", config.apiBaseUrl)
                .putString("commander_token", config.commanderToken)
                .apply()
        }
    }
}
