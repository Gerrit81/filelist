# GitHub 版本更新指南

## 推荐方式：覆盖更新 + Git Tag 标记版本

> 对于个人/小团队项目，**直接在 main 分支覆盖提交**是最简单的方式。版本号通过 Git Tag 标记。

---

## 方法一：浏览器手动上传（推荐新手）

### 首次创建仓库
1. 登录 GitHub，右上角点 **"+"** → **New repository**
2. 填写仓库名（如 `filelist`），不要勾选任何初始化选项
3. 点击 **Create repository**

### 上传代码
4. 创建成功后，页面会显示 **"…or create a new repository on the command line"** 区域
5. 往下翻到 **"…or push an existing repository from the command line"**
6. 或者直接点页面上的 **"uploading an existing file"** 链接
7. 将项目文件夹所有文件拖拽进去，写提交信息，点 **Commit changes**

### 后续更新（覆盖方式）
8. 打开仓库 → **Add file** → **Upload files**
9. 将更新后的文件拖进去（同名文件会自动覆盖）
10. 写提交信息，如 `v2.1.0 更新`，点 **Commit changes**

### 标记版本号（可选但推荐）
11. 仓库首页 → 右侧 **Releases** → **Create a new release**
12. Tag 填 `v2.1.0`，Release title 填 `v2.1.0`
13. 描述里写更新内容，点 **Publish release**
14. 用户就可以下载对应版本的 zip 包

---

## 方法二：Git 命令行（更专业）

```bash
# 首次：克隆仓库到本地
git clone https://github.com/你的用户名/filelist.git
cd filelist

# 把项目文件复制进来
cp -r /你的项目路径/* .

# 提交
git add .
git commit -m "v2.1.0 更新"
git push origin main

# 打版本标签
git tag v2.1.0
git push origin v2.1.0
```

```bash
# 后续更新：拉取最新 → 覆盖文件 → 提交
git pull origin main
# ... 修改文件 ...
git add .
git commit -m "v2.2.0 更新"
git push origin main
git tag v2.2.0
git push origin v2.2.0
```

---

## GitHub Release 上要不要传全量 zip？

| 方式 | 说明 | 适用场景 |
|------|------|----------|
| **只打 Tag** | `git tag v2.1.0` 然后 push，GitHub 自动生成源码 zip | 开发者和懂 Git 的用户 |
| **Release + 上传 zip** | 在 Release 页面手动上传一个打包好的 zip | 让不熟悉 Git 的用户也能下载 |

**建议：两个都做。** Tag 自动生成源码包，同时在 Release 里写更新说明。

---

## 本项目当前文件清单

上传到 GitHub 时，以下文件需要包含：

```
filelist/
├── index.php            # 入口文件
├── config.php           # 配置文件
├── functions.php        # 核心函数库
├── template.php         # HTML 模板
├── favicon.svg          # 网站图标
├── web.config           # IIS 配置
├── README.md            # 项目说明
├── CHANGELOG.md         # 更新日志
├── 发布教程.md           # 发布教程
├── assets/              # 静态资源（v2.1.0 新增）
│   ├── style.css
│   └── script.js
├── admin/               # 管理后台
│   ├── index.php
│   ├── login.php
│   ├── sidebar.php
│   └── ...
└── data/                # 示例数据目录（可为空）
```

以下文件 **不应上传**：
- `config.json` — 运行时自动生成，包含本地路径
- `app.db` — 数据库文件，包含用户密码
- `cache/` — 缓存目录
- `thumbs/` — 缩略图
- `session/` — 会话文件
- `backups/` — 本地备份

建议在仓库根目录添加 `.gitignore`：
```
config.json
app.db
cache/
thumbs/
session/
backups/
*.db.bak
```
