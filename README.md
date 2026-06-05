## 已迁移到 PicLite

这个仓库是旧的 EasyImages2.0 fork，已停止维护并归档。

新的维护版本已经迁移到：

- 项目仓库：https://github.com/Lau0x/easyimage
- Docker 镜像：`ghcr.io/lau0x/easyimage:latest`
- 上游项目：https://github.com/icret/EasyImages2.0

新项目名为 **PicLite**，仍然保留无数据库结构、`/i/` 图片目录和 Docker 部署兼容性。

## 推荐部署

```sh
git clone https://github.com/Lau0x/easyimage.git
cd easyimage
docker compose pull
docker compose up -d
docker compose logs easyimage
```

首次安装时，请在日志里查看安装 Token，然后访问 `http://localhost:8080/install` 完成初始化。

## 迁移旧数据

如果你已经使用过 EasyImages2.0 或这个旧 fork，通常只需要备份并迁移这些目录：

- `i/`
- `config/`
- `admin/logs/`

PicLite 默认继续使用这些目录，不需要数据库迁移。

## License

PicLite 继续遵循 GPL-2.0，并基于 EasyImages2.0 继续维护。感谢原作者 Icret 和上游贡献者。
