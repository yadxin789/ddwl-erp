# AI 助手真实模型与工具调用接入-v1.0

日期：2026-11-11

## 1. 接入结论

本系统没有引入参考项目依赖的 `laravel/ai` 包，而是直接使用 Laravel `Http`（Guzzle）调用 OpenAI 兼容的 `/chat/completions`，实现真实大模型对话与 Function Calling 工具循环。

- 参考项目：`E:\my-pm\pm-server-openEuler\app\Services\AiService.php`
- 本系统服务：`app\Services\AiService.php`
- 工具契约：`app\Ai\Contracts\AiTool.php`
- 轻量工具实现：`app\Ai\Tools\ClosureTool.php`
- 工具注册表：`app\Ai\ToolRegistry.php`
- 控制器接入：`app\Http\Controllers\AiChatController.php::send`

## 2. 请求流程

1. 用户在 `/ai/chat` 顶部模型下拉中选择已启用的服务商。
2. `AiChatController::send` 保存用户消息与附件，取其前 12 条会话历史。
3. 按所选技能的 `config.tools` 过滤工具；无技能或工具为空时使用全部工具。
4. `AiService::chatWithTools` 发送首轮请求，携带 `tools` 与 `tool_choice=auto`。
5. 模型返回 `tool_calls` 后，逐个执行 `ToolRegistry::execute`，把结果以 `role=tool` + `tool_call_id` 回填。
6. 循环最多 6 轮；模型给出最终文本后写入 `ai_messages`，并在 `meta.tools_used` 记录工具名、参数与结果摘要。
7. 未选择服务商时仍使用 `BusinessQaService` 的内置固定问答。

## 3. 服务商配置

管理员在 `/ai/providers` 添加：

| 字段 | 示例 |
| --- | --- |
| 名称 | DeepSeek 生产 |
| provider_code | `deepseek` / `openai` / `azure_openai` |
| Base URL | `https://api.deepseek.com/v1` 或 `https://api.openai.com/v1` |
| API Key | 服务商控制台创建的 Key |
| 模型 | `deepseek-chat` / `gpt-4o-mini` 等 |

- Base URL 可以带版本路径，也可以直接填完整的 `/chat/completions` 地址。
- API Key 使用 Laravel `encrypted` cast 落库，列表与接口不返回明文。
- 新添加的服务商默认启用。
- 页面「测试」按钮会真实请求一次 `/chat/completions`，提示词固定为“请只回复四个字：连接成功”。
- `provider_code` 含 `azure` 时使用 `api-key` 请求头，其余使用 `Authorization: Bearer`。

## 4. 只读工具清单

| 工具 | 能查什么 | 权限/范围 |
| --- | --- | --- |
| `QueryPmOverview` | 研发项目、逾期任务、本月销售、未完工生产工单、库存物料、待我审批 | 按账号拥有的模块权限逐项统计；全部无权限时只返回待审批数 |
| `QueryProjects` | 研发项目列表、阶段、进度、负责人 | `rnd.view`；非 `all` 数据范围只返回本人创建或本人有任务的项目 |
| `QueryProjectDetail` | 项目详情、成员任务、交付物、风险 | `rnd.view`；同上 |
| `QueryTasks` | 研发项目任务、执行人、审核人、到期、逾期 | `rnd.view`；只查可见项目下的任务 |
| `QueryUsers` | 员工姓名、账号、部门、岗位、状态 | `rnd.view` 或 `ai.view`；不返回手机号/邮箱/密码 |
| `QueryDepartments` | 部门编码、名称、负责人、状态 | 登录即可 |
| `QueryKnowledge` | 知识库文档（`media_files.category=collab_doc`） | 登录即可 |
| `SearchKnowledgeBase` | 知识库文件名/正文关键词检索 | 登录即可 |
| `QueryFiles` | 研发项目交付物及审核状态 | `rnd.view`；只查可见项目 |
| `QueryResourceFiles` | 资料文件库（`media_files.category=resource`） | 登录即可 |
| `QueryTodos` | 当前账号未完成待办 | 仅本人 |
| `QuerySalesOrders` | 销售订单号、客户、日期、金额、状态、销售员 | `sales.view`；按 `salesperson_id` 数据范围 |
| `QueryInventory` | 物料可用量、预留量、仓库、批次 | `inventory.view`；按本部门仓库数据范围；租户账号不开放 |
| `QueryPendingApprovals` | 待我审批的流程实例 | 仅本人 |
| `QueryProductionOrders` | 生产工单、产品、计划/完工/不良数量、状态 | `production.view` 或 `production.order.view`；按创建人/部门范围；租户账号不开放 |
| `QueryCustomers` | 客户编码、名称、联系人、付款条件、信用额度 | `customer.view`；按 `owner_id` 数据范围；不返回电话/邮箱/地址 |

## 5. 安全约束

- 每个工具在 `execute` 内自行校验权限、租户和数据范围，系统提示词不能替代这些校验。
- 模型只能看到当前账号可见的数据；工具返回“没有权限”时提示词要求如实转达。
- 当前只开放只读工具，没有 `Propose*` 变更提案能力；模型不得声称已新增、修改、删除或审批业务数据。
- 系统提示词禁止输出 API Key、密码、Token、手机号、邮箱、地址等敏感信息。
- 图片只在视觉模型下转 base64 发送，单张上限 5MB；非视觉模型只发送文件名说明。
- 文本附件读取前 8000 字；`docx` 从 `word/document.xml` 提取文本。

## 6. 验证

```powershell
cd E:\ddwl-erp\erp
C:\xampp\php\php.exe artisan test --filter=AiToolCallingTest
C:\xampp\php\php.exe artisan view:cache
C:\xampp\php\php.exe artisan test
```

已覆盖：

- 模型返回 `tool_calls` → 执行 `QuerySalesOrders` → 第二次请求带回 `role=tool` 结果 → 最终回答落库，并记录 `meta.tools_used`。
- 钱七可查销售订单；王五查销售被拒。
- 上传文本附件后，正文确实进入发往模型的 messages。
- 管理员触发服务商测试时，请求真实打到 `/chat/completions`。

测试使用 `Http::fake` 模拟服务商响应，不依赖真实 Key。真实 DeepSeek/OpenAI 联调需先在 `/ai/providers` 填入可用 Base URL 与 API Key。

## 7. 尚未移植

参考项目的以下能力未移植，当前系统保持只读：

- `Propose*` 两阶段变更提案、`ai_change_proposals`、`ai_delegation_tokens`、AI 授权中心。
- ZIP 工作区与 `ListWorkspaceFiles` / `ReadWorkspaceFile`。
- `ai_doc_templates` 与 `GetDocTemplate` 文档模板生成。
- 参考 `AiModel::usable()` 多模型能力；当前一个 `ai_providers` 记录对应一个模型。
