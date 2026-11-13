# OnlyOffice 文件型协同接入说明

本系统的“协同办公”已按参考项目改为 **OnlyOffice DocumentServer 文件型协同**：文档真实存放在
`storage/app/file-repository`，浏览器通过 iframe 打开 DocumentServer 编辑器，保存由
DocumentServer 回调本系统完成，版本与历史快照保存在同一磁盘。

> 注意：OnlyOffice **桌面版编辑器（DocumentServer 之外的 .exe 应用）不能被 iframe 调用**，
> 本系统需要的是 DocumentServer 服务。若本地下载的是桌面版，请另外安装 DocumentServer。

## 1. 启动 DocumentServer

### Windows 原生安装

安装 OnlyOffice DocumentServer for Windows 后，确认服务已启动：

```powershell
Get-Service | Where-Object { $_.Name -match 'OnlyOffice' }
Invoke-WebRequest -Uri 'http://127.0.0.1:8080/healthcheck' -UseBasicParsing
```

`healthcheck` 返回 `true` 即表示服务可用，默认端口为 `8080`。

### Docker（Docker Desktop）

```powershell
docker run -d --name onlyoffice-documentserver -p 8080:80 `
  -e JWT_ENABLED=true -e JWT_SECRET=change-me `
  onlyoffice/documentserver
```

Docker 模式下，DocumentServer 在容器内访问本系统，`ONLYOFFICE_PM_INTERNAL_URL` 不能写
`127.0.0.1`，应使用：

```dotenv
ONLYOFFICE_PM_INTERNAL_URL=http://host.docker.internal:8000
```

## 2. 配置 `.env`

```dotenv
ONLYOFFICE_ENABLED=true
ONLYOFFICE_SERVER_URL=http://127.0.0.1:8080
ONLYOFFICE_PM_INTERNAL_URL=http://127.0.0.1:8000
ONLYOFFICE_JWT_SECRET=
```

- `ONLYOFFICE_SERVER_URL`：浏览器访问 DocumentServer 的地址。
- `ONLYOFFICE_PM_INTERNAL_URL`：DocumentServer 访问本系统的地址，用于下载文档和保存回调。
- `ONLYOFFICE_JWT_SECRET`：DocumentServer 开启 JWT 时必填，且必须与 DocumentServer 的
  `services.CoAuthoring.secret` 一致；未开启 JWT 时留空。

修改后执行：

```powershell
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan view:clear
C:\xampp\php\php.exe artisan view:cache
```

## 3. 验证

1. 以研发经理（如“王明”）登录，进入“产品研发 → 项目详情 → 协同办公”。
2. 新建一个 Word/Excel/PPT 文档，或上传 `.docx/.xlsx/.pptx`。
3. 点击“编辑”，页面应在 iframe 中打开 OnlyOffice 编辑器。
4. 修改并等待自动保存后，关闭页面重新打开，内容应保留；列表中的版本号会递增。

若页面提示“OnlyOffice 编辑器加载失败”，按顺序检查：

1. `http://127.0.0.1:8080/healthcheck` 是否返回 `true`；
2. 浏览器能否直接打开 `ONLYOFFICE_SERVER_URL`；
3. `ONLYOFFICE_PM_INTERNAL_URL` 是否是 DocumentServer 能访问到的地址；
4. 开启 JWT 时，`ONLYOFFICE_JWT_SECRET` 是否与 DocumentServer 一致。

## 4. 数据与回退

- 文档文件：`storage/app/file-repository/collab/{project_id}/`
- 历史快照：`storage/app/file-repository/collab/{project_id}/history/`，保留最近 2 个版本
- 内置模板：`storage/app/templates/template.docx|xlsx|pptx`
- 旧数据库正文文档首次打开时会自动转换为 `.docx`，原 `content` 保留作为文本预览；
  已转换的 Office 文件不再允许用纯文本保存覆盖。

关闭协同功能：

```dotenv
ONLYOFFICE_ENABLED=false
```

关闭后协同列表仍可查看、下载已有文件，“编辑”页会提示协同功能已关闭，不再加载编辑器。
