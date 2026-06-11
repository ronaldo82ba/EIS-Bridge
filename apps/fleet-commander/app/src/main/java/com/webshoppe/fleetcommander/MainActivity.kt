package com.webshoppe.fleetcommander

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.Button
import androidx.compose.material3.Checkbox
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateListOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import org.json.JSONObject

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            CommanderScreen(application as CommanderApplication)
        }
    }
}

@Composable
fun CommanderScreen(app: CommanderApplication) {
    var apiBaseUrl by remember { mutableStateOf(app.config.apiBaseUrl) }
    var commanderToken by remember { mutableStateOf(app.config.commanderToken) }
    var packageName by remember { mutableStateOf("ph.bai.kahero") }
    var shellCommand by remember { mutableStateOf("getprop ro.build.version.release") }
    var statusText by remember { mutableStateOf("Ready") }
    var selectAll by remember { mutableStateOf(false) }

    val agents = remember { mutableStateListOf<AgentSummary>() }
    val selected = remember { mutableStateListOf<String>() }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .padding(16.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        OutlinedTextField(
            value = apiBaseUrl,
            onValueChange = { apiBaseUrl = it },
            label = { Text("API Base URL") },
            modifier = Modifier.fillMaxWidth(),
        )
        OutlinedTextField(
            value = commanderToken,
            onValueChange = { commanderToken = it },
            label = { Text("Commander Token") },
            modifier = Modifier.fillMaxWidth(),
        )
        Button(onClick = {
            app.updateConfig(CommanderConfig(apiBaseUrl, commanderToken))
            statusText = "Settings saved"
        }) {
            Text("Save Settings")
        }

        Row {
            Button(onClick = {
                Thread {
                    try {
                        val client = FleetApiClient(CommanderConfig(apiBaseUrl, commanderToken))
                        val list = client.listAgents()
                        agents.clear()
                        agents.addAll(list)
                        statusText = "Loaded ${list.size} agents"
                    } catch (exception: Exception) {
                        statusText = exception.message ?: "Refresh failed"
                    }
                }.start()
            }) {
                Text("Refresh Devices")
            }
            Checkbox(checked = selectAll, onCheckedChange = {
                selectAll = it
                selected.clear()
                if (it) selected.addAll(agents.map { agent -> agent.agentId })
            })
            Text("Select ALL")
        }

        LazyColumn(modifier = Modifier.weight(1f)) {
            items(agents) { agent ->
                Row(modifier = Modifier.fillMaxWidth()) {
                    Checkbox(
                        checked = selected.contains(agent.agentId),
                        onCheckedChange = { checked ->
                            if (checked) selected.add(agent.agentId) else selected.remove(agent.agentId)
                        },
                    )
                    Column {
                        Text(agent.agentId)
                        Text("${agent.deviceModel} • ${agent.status}")
                    }
                }
            }
        }

        OutlinedTextField(
            value = packageName,
            onValueChange = { packageName = it },
            label = { Text("Package Name") },
            modifier = Modifier.fillMaxWidth(),
        )
        OutlinedTextField(
            value = shellCommand,
            onValueChange = { shellCommand = it },
            label = { Text("Shell Command") },
            modifier = Modifier.fillMaxWidth(),
        )

        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            CommandButton("Reboot") { dispatch(app, apiBaseUrl, commanderToken, selected, selectAll, "reboot", JSONObject()) { statusText = it } }
            CommandButton("Status") { dispatch(app, apiBaseUrl, commanderToken, selected, selectAll, "device-status", JSONObject()) { statusText = it } }
            CommandButton("Clear") { dispatch(app, apiBaseUrl, commanderToken, selected, selectAll, "clear-cache", JSONObject().put("package", packageName)) { statusText = it } }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            CommandButton("Launch") { dispatch(app, apiBaseUrl, commanderToken, selected, selectAll, "launch-app", JSONObject().put("package", packageName)) { statusText = it } }
            CommandButton("Stop") { dispatch(app, apiBaseUrl, commanderToken, selected, selectAll, "stop-app", JSONObject().put("package", packageName)) { statusText = it } }
            CommandButton("Shell") { dispatch(app, apiBaseUrl, commanderToken, selected, selectAll, "execute-shell", JSONObject().put("command", shellCommand)) { statusText = it } }
        }
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            CommandButton("Logs") { dispatch(app, apiBaseUrl, commanderToken, selected, selectAll, "pull-logs", JSONObject().put("lines", 200)) { statusText = it } }
        }

        Text(statusText)
    }
}

@Composable
private fun CommandButton(label: String, onClick: () -> Unit) {
    Button(onClick = onClick) { Text(label) }
}

private fun dispatch(
    app: CommanderApplication,
    apiBaseUrl: String,
    commanderToken: String,
    selected: List<String>,
    selectAll: Boolean,
    command: String,
    payload: JSONObject,
    onStatus: (String) -> Unit,
) {
    Thread {
        try {
            val targets: Any = when {
                selectAll -> "ALL"
                selected.size == 1 -> selected.first()
                selected.isEmpty() -> throw IllegalStateException("Select at least one device")
                else -> selected
            }
            val client = FleetApiClient(CommanderConfig(apiBaseUrl, commanderToken))
            val result = client.dispatch(command, targets, payload)
            onStatus("${result.optString("status")} • ${result.optJSONObject("summary")}")
        } catch (exception: Exception) {
            onStatus(exception.message ?: "Command failed")
        }
    }.start()
}
