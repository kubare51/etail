# RunPod GPU Job Protocol

> These rules are NON-NEGOTIABLE for any GPU job on RunPod.
> Born from $0.50 wasted and 90 min of failures on 2026-06-13.

## Before ANY Full Run

1. `--test 500` first — validates model loads, dims correct, no OOM
2. Report to user BEFORE starting:
   ```
   Benchmark: X chunks/sec | VRAM: Y/Z GB
   Estimate:  N min for M chunks (× 2.0 safety = P min)
   Cost:      ~$Q at $R/hr
   Proceed?
   ```
3. Wait for user ACK
4. Use `nohup python3 script.py > ~/job.log 2>&1 &`

## Batch Size Rules

| Model | VRAM | Max Batch |
|---|---|---|
| BGE-M3 (8K context) | 48 GB | 64 |
| BGE-large (512 context) | 48 GB | 512 |

## Script Rules

- Use `sys.stdout.flush()` after every print (nohup buffers stdout)
- Sort chunks by text length before encoding (5x speedup proven)
- Save checkpoints every 1000 chunks
- Support `--resume` flag
- Don't save texts.json — use local chunks.jsonl (saves 90 MB download)

## SSH Rules

- NEVER run GPU jobs as foreground SSH process
- Always `nohup` + log file
- Use separate SSH commands for monitoring: `tail -5 ~/job.log`
- Upload .py files via SCP — never inline Python in SSH command (PowerShell breaks it)
- Always use `--break-system-packages` for pip on containers

## Monitoring

- Check progress: `ssh pod "tail -5 ~/job.log"`
- Check GPU: `ssh pod "nvidia-smi --query-gpu=memory.used,utilization.gpu --format=csv,noheader"`
- Check process: `ssh pod "ps aux | grep python3"`

## After Completion

1. Download output: `scp -P PORT -r pod:~/data/output/embeddings/ ~/.logimax/embeddings/`
2. Load locally: `python load_chroma.py --embeddings ~/.logimax/embeddings/ --chunks path/to/chunks.jsonl --rebuild`
3. Verify: `lca semantic "test query"`
4. **STOP THE POD** immediately
