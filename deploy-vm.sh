#!/bin/bash
USERNAME="$1"
PASSWORD="$2"
VMID="$3"
PLAN="$4"
OS="$5"

TEMPLATE_DEBIAN=1121
TEMPLATE_FEDORA=1131
TEMPLATE_UBUNTU_SERVER=1141
TEMPLATE_CENTOS_STREAM=1151
TEMPLATE_ALPINE=1161

case "$PLAN" in
  free)
    RAM=512
    CORES=1
    DISK_SIZE=2
    ;;
  personal)
    RAM=2048
    CORES=1
    DISK_SIZE=10
    ;;
  basic)
    RAM=4000
    CORES=2
    DISK_SIZE=25
    ;;
  pro)
    RAM=8000
    CORES=4
    DISK_SIZE=45
    ;;
  smallbusiness)
    RAM=18000
    CORES=8
    DISK_SIZE=95
    ;;
  *)
    echo "Invalid plan"
    exit 1
    ;;
esac

echo "Creating Proxmox user..."
pveum useradd ${USERNAME}@pve -password ${PASSWORD}

case "$OS" in
  debian)
    echo "Cloning from Debian template..."
    TEMPLATE_ID=$TEMPLATE_DEBIAN
    TEMPLATE_SIZE=10
    ;;
  fedora)
    echo "Cloning from Fedora template..."
    TEMPLATE_ID=$TEMPLATE_FEDORA
    TEMPLATE_SIZE=10
    ;;
  ubuntu-server)
    echo "Cloning from Ubuntu Server template..."
    TEMPLATE_ID=$TEMPLATE_UBUNTU_SERVER
    TEMPLATE_SIZE=10
    ;;
  centos)
    echo "Cloning from CentOS Stream template..."
    TEMPLATE_ID=$TEMPLATE_CENTOS_STREAM
    TEMPLATE_SIZE=10
    ;;
  alpine)
    echo "Cloning from Alpine template..."
    TEMPLATE_ID=$TEMPLATE_ALPINE
    TEMPLATE_SIZE=10
    ;;
  *)
    echo "Invalid OS selection"
    exit 1
    ;;
esac

echo "Cloning VM to terabyte storage..."
qm clone $TEMPLATE_ID $VMID --name $USERNAME --full --storage terabyte

echo "Configuring cloned VM..."
qm set $VMID --memory $RAM --cores $CORES

if [ $DISK_SIZE -gt $TEMPLATE_SIZE ]; then
    echo "Resizing disk to ${DISK_SIZE}GB..."
    qm resize $VMID sata0 ${DISK_SIZE}G
else
    echo "Disk size ${DISK_SIZE}GB is smaller than or equal to template, skipping resize."
fi

echo "Assigning permissions..."
pveum aclmod /vms/$VMID -user ${USERNAME}@pve -role PVEVMUser
