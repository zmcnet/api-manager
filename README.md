# API Manager · AI API 配置管理中心

> 集中管理各种 AI 服务的 API 配置（API Key、Base URL、API 路径后缀、模型列表等），
> 方便用户在不同 AI 应用之间快速复制配置。

## 核心特性

- **零信任加密** — API Key 使用 AES-256-CBC + HMAC 加密，每个用户拥有独立密钥；
  管理员后台**无法**读取任何用户的 API Key
- **多用户系统** — 邮箱注册、SMTP 验证码、密码重置、记住我、CSRF 防护
- **专为开发者设计** — 暗色主题，响应式，紧凑信息密度
- **一键复制** — 完整 URL / API 格式后缀 / API Key / 完整 JSON 配置，多种复制粒度
- **预设模板** — 内置 OpenAI / Claude / GLM / DeepSeek / 通义千问 五大常用 API 模板
- **模型管理** — 每个供应商支持多个模型（昵称 + 真实 ID + 上下文窗口），支持拖拽排序
- **后台管理** — 用户管理、SMTP 配置、系统设置、统计仪表盘

---

## 技术栈

| 组件 | 版本 |
| ---- | ---- |
| PHP | **8.2+**（使用 `never` 返回类型、`readonly` 等特性） |
| MySQL | **5.7+**（兼容，未用 8.0+ 特性） |
| Nginx | **1.26+** |
| 前端 | 原生 HTML / CSS / JS，**零依赖** |
| 邮件 | 内置 SMTP 客户端（无需 PHPMailer） |

> 无需 Composer — 所有依赖（加密、CSRF、路由、邮件）均为项目内置实现。

---

## 快速部署

### 1. 上传项目

```bash
git clone <this-repo> /var/www/api-manager
chown -R www-data:www-data /var/www/api-manager
chmod -R 755 /var/www/api-manager
chmod 775 /var/www/api-manager   # 安装向导需写入 config.php
```

### 2. 配置 Nginx

```bash
cp /var/www/api-manager/nginx.conf.example /etc/nginx/sites-available/api-manager.conf
# 修改 server_name、root、php-fpm socket 路径
ln -s /etc/nginx/sites-available/api-manager.conf /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
```

### 3. 创建数据库（可选，安装向导会自动创建）

```bash
mysql -u root -p -e "CREATE DATABASE api_manager DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 4. 运行安装向导

浏览器打开 `http://your-domain.com/install.php`：

1. **环境检查** — 自动检测 PHP / MySQL / OpenSSL 等
2. **数据库** — 填写连接信息，自动建库 + 导入 SQL
3. **管理员** — 设置邮箱 + 密码 + 站点名 + 站点 URL
4. **完成** — 自动生成 `config.php` 并跳转登录

> ⚠️ **安装完成后请立即删除 `install.php`**，避免被恶意触发重装：
> ```bash
> rm /var/www/api-manager/install.php
> ```

### 5. 配置 SMTP（用于注册验证 / 密码重置）

登录管理员 → 「后台」→「SMTP 配置」：

| 项 | 值 |
| -- | -- |
| 主机 | `smtp.qq.com` / `smtp.163.com` / `smtp-mail.outlook.com` 等 |
| 端口 | `465`（SSL）或 `587`（TLS） |
| 加密 | SSL / TLS / 无 |
| 用户名 | 完整邮箱 |
| 密码 | 邮箱**授权码**（非登录密码） |
| 发件邮箱 | 与用户名一致 |

勾选「启用此配置」保存，可用「测试发送」验证。

---

## 本地开发

```bash
git clone <this-repo>
cd api-manager

# PHP 内置服务器（开发用）
php -S localhost:8080 -t .
# 打开 http://localhost:8080/install.php
```

数据库准备好后按向导即可。

---

## 构建说明

本项目为**原生 PHP，无编译步骤、无打包器**。

| 目录 | 用途 |
| ---- | ---- |
| `index.php` | 前端入口（路由分发） |
| `install.php` | 安装向导 |
| `bootstrap.php` | 应用引导（自动加载、配置读取） |
| `config.example.php` | 配置模板（安装向导据此生成 `config.php`） |
| `src/` | 核心类 |
| `controllers/` | 控制器 |
| `views/` | 视图模板 |
| `public/assets/` | 静态资源（CSS / JS） |
| `sql/install.sql` | 数据库结构 |
| `router.php` | 内置服务器路由 |
| `nginx.conf.example` / `bt-rewrite.conf` | Nginx / 宝塔 rewrite 示例 |

**`src/` 核心类**：`Auth` `Crypto` `Csrf` `Database` `Helper` `Mailer` `Presets` `Router` `Session`

---

## 安全说明

- `config.php` 由安装向导生成，含数据库密码与应用密钥，已列入 `.gitignore`，
  **切勿提交到版本库**
- 每个用户的 API Key 由用户自己的 `app_key` 派生密钥加密，
  管理员亦无法解密查看
- 所有写操作均有 CSRF 校验，密码使用哈希存储

---

## 目录结构

```
api-manager/
├── index.php
├── install.php
├── bootstrap.php
├── config.example.php
├── router.php
├── nginx.conf.example
├── bt-rewrite.conf
├── sql/install.sql
├── src/
│   ├── Auth.php  Crypto.php  Csrf.php  Database.php
│   ├── Helper.php  Mailer.php  Presets.php  Router.php  Session.php
├── controllers/
│   ├── AuthController.php  AdminController.php
│   ├── HomeController.php  ModelController.php  ProviderController.php
├── views/
│   ├── layouts/  auth/  admin/  errors/
│   ├── dashboard.php  provider_form.php
└── public/assets/
```

---

## 许可

MIT