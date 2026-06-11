package com.webshoppe.fleetagent

import android.app.admin.DevicePolicyManager
import android.content.ComponentName
import android.content.Intent
import android.os.Bundle
import android.widget.Button
import android.widget.EditText
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONObject

class MainActivity : AppCompatActivity() {

    private lateinit var config: AgentConfig
    private var httpServer: AgentHttpServer? = null

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(buildLayout())

        config = (application as FleetAgentApplication).config
        bindFields()
        startServices()
    }

    private fun buildLayout(): android.widget.LinearLayout {
        val layout = android.widget.LinearLayout(this).apply {
            orientation = android.widget.LinearLayout.VERTICAL
            setPadding(32, 32, 32, 32)
        }

        layout.addView(EditText(this).apply { hint = "Agent ID"; tag = "agent_id" })
        layout.addView(EditText(this).apply { hint = "API Base URL"; tag = "api_base_url" })
        layout.addView(EditText(this).apply {
            hint = "Agent Token"
            tag = "agent_token"
            inputType = android.text.InputType.TYPE_CLASS_TEXT or android.text.InputType.TYPE_TEXT_VARIATION_PASSWORD
        })
        layout.addView(Button(this).apply {
            text = "Save & Register"
            setOnClickListener { saveAndRegister() }
        })
        layout.addView(Button(this).apply {
            text = "Enable Device Owner"
            setOnClickListener { requestDeviceAdmin() }
        })
        layout.addView(TextView(this).apply { tag = "status"; text = "Status: idle" })

        return layout
    }

    private fun bindFields() {
        findField<EditText>("agent_id").setText(config.agentId)
        findField<EditText>("api_base_url").setText(config.apiBaseUrl)
        findField<EditText>("agent_token").setText(config.agentToken)
    }

    private fun saveAndRegister() {
        config = AgentConfig(
            agentId = findField<EditText>("agent_id").text.toString(),
            apiBaseUrl = findField<EditText>("api_base_url").text.toString(),
            agentToken = findField<EditText>("agent_token").text.toString(),
            httpPort = config.httpPort,
            pollIntervalSec = config.pollIntervalSec,
        )
        AgentConfig.save(this, config)
        (application as FleetAgentApplication).updateConfig(config)

        val body = JSONObject()
            .put("agent_id", config.agentId)
            .put("device_serial", android.os.Build.SERIAL)
            .put("device_model", android.os.Build.MODEL)
            .put("callback_base_url", "http://127.0.0.1:${config.httpPort}")
            .toString()
            .toRequestBody("application/json".toMediaType())

        val request = Request.Builder()
            .url("${config.apiBaseUrl.trimEnd('/')}/fleet/agents/register")
            .post(body)
            .build()

        Thread {
            OkHttpClient().newCall(request).execute().use { response ->
                val json = JSONObject(response.body?.string() ?: "{}")
                val token = json.optString("token")
                if (token.isNotBlank()) {
                    config = config.copy(agentToken = token)
                    AgentConfig.save(this, config)
                    (application as FleetAgentApplication).updateConfig(config)
                }
                runOnUiThread {
                    findField<TextView>("status").text = "Registered: ${response.code}"
                    startServices()
                }
            }
        }.start()
    }

    private fun requestDeviceAdmin() {
        val intent = Intent(DevicePolicyManager.ACTION_ADD_DEVICE_ADMIN).apply {
            putExtra(DevicePolicyManager.EXTRA_DEVICE_ADMIN, ComponentName(this@MainActivity, DeviceAdminReceiver::class.java))
        }
        startActivity(intent)
    }

    private fun startServices() {
        httpServer?.stop()
        if (config.agentToken.isNotBlank()) {
            httpServer = AgentHttpServer(config.httpPort, config, CommandEngine(this)).also { it.start() }
            startForegroundService(Intent(this, FleetPollService::class.java))
            findField<TextView>("status").text = "Agent running on port ${config.httpPort}"
        }
    }

    @Suppress("UNCHECKED_CAST")
    private fun <T> findField(tag: String): T = findViewWithTag(tag) as T
}
