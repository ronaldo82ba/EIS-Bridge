package com.webshoppe.fleetagent

import fi.iki.elonen.NanoHTTPD
import org.json.JSONObject
import java.util.Locale

class AgentHttpServer(
    port: Int,
    private val config: AgentConfig,
    private val engine: CommandEngine,
) : NanoHTTPD(port) {

    override fun serve(session: IHTTPSession): Response {
        if (!isAuthorized(session)) {
            return jsonResponse(
                JSONObject().put("error", "unauthorized").put("message", "Missing or invalid agent token."),
                NanoHTTPD.Response.Status.UNAUTHORIZED,
            )
        }

        val uri = session.uri.trim('/').lowercase(Locale.US)
        val command = uri.substringAfterLast('/')

        if (session.method != Method.POST) {
            return jsonResponse(
                JSONObject().put("error", "method_not_allowed"),
                NanoHTTPD.Response.Status.METHOD_NOT_ALLOWED,
            )
        }

        val payload = readJsonBody(session)

        return try {
            val result = engine.execute(command, payload)
            jsonResponse(result, NanoHTTPD.Response.Status.OK)
        } catch (exception: Exception) {
            jsonResponse(
                JSONObject().put("success", false).put("error", exception.message),
                NanoHTTPD.Response.Status.UNPROCESSABLE_ENTITY,
            )
        }
    }

    private fun isAuthorized(session: IHTTPSession): Boolean {
        val token = session.headers["x-agent-token"] ?: session.headers["X-Agent-Token"]
        return token != null && token == config.agentToken
    }

    private fun readJsonBody(session: IHTTPSession): JSONObject {
        val files = HashMap<String, String>()
        session.parseBody(files)
        val body = files["postData"] ?: ""
        return if (body.isBlank()) JSONObject() else JSONObject(body)
    }

    private fun jsonResponse(body: JSONObject, status: NanoHTTPD.Response.Status): Response {
        return newFixedLengthResponse(status, "application/json", body.toString())
    }
}
